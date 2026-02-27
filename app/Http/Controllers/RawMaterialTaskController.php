<?php

namespace App\Http\Controllers;

use App\Models\RawMaterialTask;
use App\Models\RawMaterialBatch;
use App\Models\RawMaterialNote;
use App\Models\ProductionOrder;
use App\Services\RawMaterialInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;


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

    // Admin lists all raw material tasks with details and notes
    public function adminListTasks(Request $request)
    { /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!($user->hasRole('admin') || $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tasks = RawMaterialTask::with(['user', 'admin'])->orderBy('created_at', 'desc')->get();

        $payload = $tasks->map(function ($t) {
            $notes = RawMaterialNote::where('raw_material_task_id', $t->id)->orderBy('created_at', 'desc')->get();

            $details = $t->details ?? [];
            $pending = $details['pending_received_items'] ?? null;
            $received = $details['received_items'] ?? null;

            // If received items were stored (as arrays with ids), load full RawMaterialBatch models
            $receivedBatches = null;
            if (is_array($received) && count($received) > 0) {
                $ids = array_values(array_filter(array_map(function ($b) {
                    return isset($b['id']) ? $b['id'] : null;
                }, $received)));

                if (!empty($ids)) {
                    $receivedBatches = \App\Models\RawMaterialBatch::whereIn('id', $ids)->with('rawMaterial')->get();
                }
            }

            return [
                'task' => $t,
                'pending_received_items' => $pending,
                'received_items' => $received,
                'received_batches' => $receivedBatches,
                'notes' => $notes,
                'notes_count' => $notes->count(),
                'unread_notes_count' => $notes->where('is_read', false)->count()
            ];
        });

        return response()->json(['tasks' => $payload]);
    }

    // Warehouse confirms receipt: create batches and mark task completed
    // Warehouse submits received items (does NOT change inventory quantities yet)
    public function submitReceiveInput(Request $request, $id)
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

        // Ensure each pending item has a provisional batch_number so user doesn't need to input it
        $processed = [];
        foreach ($items as $it) {
            $it = (array) $it;
            if (empty($it['batch_number'])) {
                $it['batch_number'] = 'PRB-'.date('YmdHis').'-'.substr(uniqid(), -6);
            }
            $processed[] = $it;
        }

        $details = $task->details ?? [];
        $details['pending_received_items'] = $processed;
        $task->details = $details;
        $task->status = 'input_submitted';
        $task->save();

        return response()->json(['message' => 'Receive input submitted', 'pending_received_items' => $processed]);
    }

    // Warehouse confirms receipt and actually creates batches (inventory increases only here)
    public function confirmReceive(Request $request, $id)
    {
        $user = Auth::user();

        $task = RawMaterialTask::where('id', $id)->where('user_id', $user->id)->where('route', 'receive')->firstOrFail();

        $details = $task->details ?? [];
        $items = $details['pending_received_items'] ?? $request->items ?? [];

        if (empty($items)) {
            return response()->json(['message' => 'No items to confirm', 'details' => $details], 422);
        }

        $created = [];
        DB::transaction(function () use ($items, $task, &$created, &$details) {
            foreach ($items as $it) {
                $batch = RawMaterialBatch::create([
                    'raw_material_id' => $it['raw_material_id'],
                    'batch_number' => $it['batch_number'] ?? null,
                    'quantity' => $it['quantity'],
                    'remaining_quantity' => $it['quantity'],
                    'expiry_date' => $it['expiry_date'] ?? null,
                    'received_at' => now()
                ]);

                // Generate automatic batch_number when not provided
                if (empty($batch->batch_number)) {
                    $batch->batch_number = 'RB-'.date('Ymd').'-'.str_pad($batch->id, 6, '0', STR_PAD_LEFT);
                    $batch->save();
                }

                $created[] = $batch->toArray();
            }

            $task->status = 'completed';
            $task->sent_at = now();
            $details['received_items'] = $created;
            unset($details['pending_received_items']);
            $task->details = $details;
            $task->save();
        });

        return response()->json(['message' => 'Receipt confirmed', 'received_batches' => $created]);
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

    // Warehouse adds a note to the task (sent to admin)
    public function addNote(Request $request, $id)
    {
        $user = Auth::user();

        $task = RawMaterialTask::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        $request->validate([
            'message' => 'required|string'
        ]);

        $note = RawMaterialNote::create([
            'raw_material_task_id' => $task->id,
            'from_user_id' => $user->id,
            'to_user_id' => $task->admin_id,
            'message' => $request->message,
            'is_read' => false
        ]);

        return response()->json(['message' => 'Note added', 'note' => $note], 201);
    }

    // Admin lists notes for a task (shows read/unread)
    public function adminListNotes(Request $request, $id)
    { /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!($user->hasRole('admin') || $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notes = RawMaterialNote::where('raw_material_task_id', $id)
            ->orderBy('created_at', 'desc')
            ->with(['fromUser'])
            ->get();

        return response()->json(['notes' => $notes]);
    }

    // Admin marks a single note as read
    public function markNoteRead(Request $request, $noteId)
    { /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!($user->hasRole('admin') || $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $note = RawMaterialNote::where('id', $noteId)->where('to_user_id', $user->id)->firstOrFail();
        $note->is_read = true;
        $note->save();

        return response()->json(['message' => 'Note marked as read']);
    }

    // Admin deletes read notes for a task
    public function deleteReadNotes(Request $request, $id)
    { /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!($user->hasRole('admin') || $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $deleted = RawMaterialNote::where('raw_material_task_id', $id)
            ->where('to_user_id', $user->id)
            ->where('is_read', true)
            ->delete();

        return response()->json(['message' => 'Deleted read notes', 'count' => $deleted]);
    }

    // Get inventory summary (for admin and warehouse)
    public function inventorySummary(Request $request)
    {
        $materials = \App\Models\RawMaterial::with(['batches' => function ($q) { $q->select('id','raw_material_id','batch_number','remaining_quantity','received_at','expiry_date'); }])->get();

        $summary = $materials->map(function ($m) {
            $total = $m->batches->sum('remaining_quantity');
            $lastReceived = null;
            if ($m->batches->isNotEmpty()) {
                $lastReceived = $m->batches->max('received_at');
            }

            return [
                'raw_material_id' => $m->id,
                'name' => $m->name,
                'unit' => $m->unit,
                'total_remaining' => $total,
                'last_received_at' => $lastReceived,
                'batches' => $m->batches
            ];
        });

        return response()->json(['inventory' => $summary]);
    }
}
