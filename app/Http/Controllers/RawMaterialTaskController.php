<?php

namespace App\Http\Controllers;

use App\Models\RawMaterialTask;
use App\Models\RawMaterialBatch;
use App\Models\ProductionOrder;
use App\Services\RawMaterialInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RawMaterialTaskController extends Controller
{
    protected $inventory;

    public function __construct(RawMaterialInventoryService $inventory)
    {
        $this->inventory = $inventory;
    }

    // Admin creates a receive task
    public function createReceiveTask(Request $request)
    {

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'scheduled_at' => 'nullable|date'
        ]);

        $task = RawMaterialTask::create([
            'admin_id' => Auth::id(),
            'user_id' => $request->user_id,
            'route' => 'receive',
            'status' => 'pending',
            'scheduled_at' => $request->scheduled_at,
            'details' => []
        ]);

        return response()->json(['message' => 'Task created', 'task' => $task], 201);
    }

    // Admin creates a send-to-production task
    public function createSendTask(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'items' => 'required|array',
            'items.*.raw_material_id' => 'required|integer|exists:raw_materials,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        // Create a ProductionOrder record and generate an auto-incremented order number
        $productionOrder = ProductionOrder::create([
            'order_number' => 'temp',
            'user_id' => $request->user_id,
            'status' => 'pending'
        ]);

        $productionOrder->order_number = 'PO-'.str_pad($productionOrder->id, 6, '0', STR_PAD_LEFT);
        $productionOrder->save();

        $task = RawMaterialTask::create([
            'admin_id' => Auth::id(),
            'user_id' => $request->user_id,
            'route' => 'send_to_production',
            'status' => 'pending',
            'details' => [
                'production_order_id' => $productionOrder->id,
                'items' => $request->items
            ]
        ]);

        return response()->json(['message' => 'Send task created', 'task' => $task, 'production_order' => $productionOrder], 201);
    }

    // Warehouse keeper lists their tasks
    public function listTasks(Request $request)
    {
        $user = Auth::user();

        $tasks = RawMaterialTask::where('user_id', $user->id )->orderBy('created_at', 'desc')->get();

        return response()->json(['tasks' => $tasks]);
    }

    // Warehouse confirms receipt: create batches and mark task completed
    public function confirmReceive(Request $request, $id)
    {
        $user = Auth::user();

        $task = RawMaterialTask::where('id', $id)->where('user_id', $user->id)->where('route', 'receive')->firstOrFail();

        $request->validate([
            'items' => 'required|array',
            'items.*.raw_material_id' => 'required|integer|exists:raw_materials,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.batch_number' => 'nullable|string',
            'items.*.expiry_date' => 'nullable|date',
        ]);

        $items = $request->items;

        DB::transaction(function () use ($items, $task) {
            $created = [];
            foreach ($items as $it) {
                $batch = RawMaterialBatch::create([
                    'raw_material_id' => $it['raw_material_id'],
                    'batch_number' => $it['batch_number'] ?? null,
                    'quantity' => $it['quantity'],
                    'remaining_quantity' => $it['quantity'],
                    'expiry_date' => $it['expiry_date'] ?? null,
                    'received_at' => now()
                ]);

                $created[] = $batch;
            }

            $task->status = 'completed';
            $task->sent_at = now();
            $task->details = ['received_items' => $created];
            $task->save();
        });

        return response()->json(['message' => 'Receipt confirmed']);
    }

    // Warehouse confirms sending to production: allocate FIFO and attach to production order
    public function confirmSend(Request $request, $id)
    {
        $user = Auth::user();

        $task = RawMaterialTask::where('id', $id)->where('user_id', $user->id)->where('route', 'send_to_production')->firstOrFail();

        $details = $task->details ?? [];
        $items = $details['items'] ?? [];
        $productionOrderId = $details['production_order_id'] ?? null;

        if (!$productionOrderId) {
            return response()->json(['message' => 'Missing production order id in task details'], 422);
        }

        $productionOrder = ProductionOrder::findOrFail($productionOrderId);

        DB::transaction(function () use ($items, $productionOrder, $task) {
            foreach ($items as $it) {
                $rawMaterialId = $it['raw_material_id'];
                $qty = $it['quantity'];

                $allocs = $this->inventory->allocateFifo($rawMaterialId, $qty);

                foreach ($allocs as $alloc) {
                    $productionOrder->rawMaterialBatches()->attach($alloc['batch_id'], ['quantity' => $alloc['quantity']]);
                }
            }

            $task->status = 'completed';
            $task->sent_at = now();
            $task->save();
        });

        return response()->json(['message' => 'Materials sent to production']);
    }

    // Get inventory summary (for admin and warehouse)
    public function inventorySummary(Request $request)
    {
        $materials = \App\Models\RawMaterial::with(['batches' => function ($q) { $q->select('id','raw_material_id','batch_number','remaining_quantity','received_at','expiry_date'); }])->get();

        $summary = $materials->map(function ($m) {
            $total = $m->batches->sum('remaining_quantity');
            return [
                'raw_material_id' => $m->id,
                'name' => $m->name,
                'unit' => $m->unit,
                'total_remaining' => $total,
                'batches' => $m->batches
            ];
        });

        return response()->json(['inventory' => $summary]);
    }
}
