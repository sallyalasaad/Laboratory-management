<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\RawMaterialTask;
use App\Models\RawMaterialBatch;
use App\Models\ProductionOrder;
use App\Services\RawMaterialInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
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
            'scheduled_at' => 'nullable|date|after_or_equal:now|before_or_equal:' . now()->addDays(30)
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
            //'finished_product_id' => 'required|exists:finished_products,id',
            'items.*.raw_material_id' => 'required|integer|exists:raw_materials,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        // Check availability for each item before creating the task
        foreach ($request->items as $item) {
            $rawMaterialId = $item['raw_material_id'];
            $requestedQuantity = $item['quantity'];

            $availableQuantity = RawMaterialBatch::where('raw_material_id', $rawMaterialId)->sum('remaining_quantity');

            if ($availableQuantity < $requestedQuantity) {
                return response()->json([
                    'message' => "Insufficient quantity for raw material ID {$rawMaterialId}. Available: {$availableQuantity}, Requested: {$requestedQuantity}"
                ], 422);
            }
        }

        $task = RawMaterialTask::create([
            'admin_id' => Auth::id(),
            'user_id' => $request->user_id,
            'route' => 'send_to_production',
            'status' => 'pending',
            'details' => [
                //'production_order_id' => $productionOrder->id,
                'items' => $request->items
            ]
        ]);

        return response()->json(['message' => 'Send task created', 'task' => $task], 201);
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
            $notes = Note::where('raw_material_task_id', $t->id)->orderBy('created_at', 'desc')->get();

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
//$task = RawMaterialTask::find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        if ($task->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($task->route !== 'receive') {
            return response()->json(['message' => 'Invalid task route'], 400);
        }
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
        if ($task->status === 'completed') {
            return response()->json([
                'message' => 'Task already completed'
            ], 400);}
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

        $task = RawMaterialTask::where('id', $id)
            ->where('user_id', $user->id)
            ->where('route', 'send_to_production')
            ->firstOrFail();

        if ($task->status === 'completed') {
            return response()->json(['message' => 'Task already completed'], 400);
        }

        $items = $task->details['items'] ?? [];

        if (empty($items)) {
            return response()->json(['message' => 'No items defined'], 422);
        }

        $createdAllocations = [];

        try {
            DB::transaction(function () use ($items, $task, &$createdAllocations) {

                foreach ($items as $it) {

                    $rawMaterialId = $it['raw_material_id'];
                    $qty = $it['quantity'];

                    // 🔥 قراءة فقط بدون خصم
                    $batches = RawMaterialBatch::where('raw_material_id', $rawMaterialId)
                        ->where('remaining_quantity', '>', 0)
                        ->orderBy('received_at', 'asc')
                        ->get();

                    foreach ($batches as $batch) {

                        if ($qty <= 0) break;

                        $take = min($batch->remaining_quantity, $qty);

                        $createdAllocations[] = [
                            'raw_material_id' => $rawMaterialId,
                            'batch_id' => $batch->id,
                            'quantity' => $take
                        ];

                        $qty -= $take;
                    }

                    if ($qty > 0) {
                        throw new \Exception("Not enough stock for raw material {$rawMaterialId}");
                    }
                }

                $details = $task->details ?? [];
                $details['sent_allocations'] = $createdAllocations;

                unset($details['items']);

                $task->details = $details;
                $task->status = 'completed';
                $task->sent_at = now();
                $task->save();
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Allocation failed',
                'error' => $e->getMessage()
            ], 422);
        }

        return response()->json([
            'message' => 'Materials prepared (no stock deducted)',
            'allocations' => $createdAllocations
        ]);
    }
    // Warehouse adds a note to the task (sent to admin)
    public function addNote(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'message' => 'required|string'
        ]);

        // جلب كل الأدمنز
        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            return response()->json(['message' => 'Admin not found'], 404);
        }

        $notes = [];

        foreach ($admins as $admin) {
            $notes[] = Note::create([
                'from_user_id' => $user->id,
                'to_user_id' => $admin->id,
                'message' => $data['message'],
                'is_read' => false
            ]);
        }

        return response()->json([
            'message' => 'Note sent to admin(s)',
            'notes' => $notes
        ], 201);
    }
    // Admin lists notes for a task (shows read/unread)
   public function adminListNotes(Request $request)
{
    /** @var \App\Models\User $user */
    $user = Auth::user();

    if (!($user->hasRole('admin') || $user->hasRole('super_admin'))) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }

    // فلتر اختياري حسب الدور
    $role = $request->role;

    $query = Note::with([
        'fromUser.roles'
    ]);

    // إذا تم إرسال role
    if ($role) {
        $query->whereHas('fromUser.roles', function ($q) use ($role) {
            $q->where('name', $role);
        });
    }

    $notes = $query
        ->orderBy('is_read', 'asc')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($note) {

            return [
                'id' => $note->id,
                'message' => $note->message,
                'is_read' => $note->is_read,

                'sender' => [
                    'id' => $note->fromUser->id ?? null,
                    'name' => $note->fromUser->name ?? null,
                    'role' =>
                        $note->fromUser
                        ->roles
                        ->pluck('name')
                        ->implode(', ')
                ],

                'created_at' => $note->created_at
            ];
        });

    return response()->json([
        'message' => 'Notes retrieved successfully',
        'notes' => $notes
    ]);
}
    // Admin marks a single note as read
    public function markNoteRead(Request $request, $noteId)
    { /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!($user->hasRole('admin') || $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $note = Note::where('id', $noteId)->where('to_user_id', $user->id)->firstOrFail();
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

        $deleted = Note::where('raw_material_task_id', $id)
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

    public function confirmReceiveinp(Request $request, $id)
    {
        $user = Auth::user();

        $task = RawMaterialTask::where('id', $id)
            ->where('route', 'send_to_production')
            ->firstOrFail();

        $details = $task->details ?? [];

        if (($task->status !== 'completed' && $task->status !== 'received_by_production')
            || empty($details['sent_allocations'])) {
            return response()->json([
                'message' => 'Task not ready'
            ], 400);
        }

        if (!empty($details['production_received_at'])) {
            return response()->json([
                'message' => 'Already received'
            ], 400);
        }

        DB::transaction(function () use ($task, $user, &$details) {

            // 🔥 الخصم الحقيقي هنا
            foreach ($details['sent_allocations'] as $alloc) {

                RawMaterialBatch::where('id', $alloc['batch_id'])
                    ->decrement('remaining_quantity', $alloc['quantity']);
            }

            $details['production_received_at'] = now();
            $details['production_received_by'] = $user->id;

            $task->details = $details;
            $task->status = 'received_by_production';
            $task->save();
        });

        return response()->json([
            'message' => 'Materials received and deducted from stock',
            'task' => $task
        ]);
    }

    // Production employee lists confirmed sent raw materials
    public function listConfirmedSentMaterials(Request $request)
    {
        $user = Auth::user();

        if (!$user->hasRole('production_employee') && !$user->hasRole('admin') && !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tasks = RawMaterialTask::where('route', 'send_to_production')
            ->whereIn('status', ['completed'])
            ->with(['user', 'admin'])
            ->orderBy('sent_at', 'desc')
            ->get();

        $formatted = $tasks->map(function ($task) {
            $details = $task->details ?? [];
            $sentAllocations = $details['sent_allocations'] ?? [];

            // Load batch details for each allocation
            $allocationsWithDetails = [];
            foreach ($sentAllocations as $alloc) {
                $batch = RawMaterialBatch::with('rawMaterial')->find($alloc['batch_id']);
                if ($batch) {
                    $allocationsWithDetails[] = [
                        'raw_material_id' => $alloc['raw_material_id'],
                        'raw_material_name' => $batch->rawMaterial->name ?? 'Unknown',
                        'batch_id' => $alloc['batch_id'],
                        'batch_number' => $batch->batch_number,
                        'quantity' => $alloc['quantity'],
                        'expiry_date' => $batch->expiry_date,
                        'received_at' => $batch->received_at,
                    ];
                }
            }

            return [
                'task_id' => $task->id,
                'sent_at' => $task->sent_at,
                'status' => $task->status,
                'warehouse_keeper' => $task->user->name ?? 'Unknown',
                'admin' => $task->admin->name ?? 'Unknown',
                'production_received_at' => $details['production_received_at'] ?? null,
                'production_received_by' => $details['production_received_by'] ?? null,
                'allocations' => $allocationsWithDetails,
            ];
        });

        return response()->json(['confirmed_sent_materials' => $formatted]);
    }

    // Display raw materials confirmed received by production employee
    public function listProductionConfirmedMaterials(Request $request)
{
    $user = Auth::user();

    if (!$user->hasRole('production_employee') && !$user->hasRole('admin') && !$user->hasRole('super_admin')) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // جلب كل المهام المتعلقة بالإرسال للإنتاج سواء كانت مستلمة أو لا تزال قيد الإرسال
    $tasks = RawMaterialTask::where('route', 'send_to_production')
        ->whereIn('status', ['completed', 'received_by_production'])
        ->with(['user', 'admin'])
        ->orderBy('updated_at', 'desc')
        ->get();

    $formatted = $tasks->map(function ($task) {
        $details = $task->details ?? [];
        $sentAllocations = $details['sent_allocations'] ?? [];

        // جلب تفاصيل الدفعات
        $allocationsWithDetails = [];
        foreach ($sentAllocations as $alloc) {
            $batch = RawMaterialBatch::with('rawMaterial')->find($alloc['batch_id']);
            if ($batch) {
                $allocationsWithDetails[] = [
                    'raw_material_id' => $alloc['raw_material_id'],
                    'raw_material_name' => $batch->rawMaterial->name ?? 'Unknown',
                    'batch_id' => $alloc['batch_id'],
                    'batch_number' => $batch->batch_number,
                    'quantity' => $alloc['quantity'],
                    'expiry_date' => $batch->expiry_date,
                    'received_at' => $batch->received_at,
                ];
            }
        }

        // الموظف الذي أكد الاستلام (إن وجد)
        $productionEmployee = null;
        if (!empty($details['production_received_by'])) {
            $productionEmployee = User::find($details['production_received_by']);
        }

        // الحالة: مؤكد الاستلام أم لا
        $isConfirmed = $task->status === 'received_by_production';

        return [
            'task_id' => $task->id,
            'sent_at' => $task->sent_at,
            'status' => $task->status,
            'is_confirmed' => $isConfirmed,
            'production_received_at' => $details['production_received_at'] ?? null,
            'production_employee' => $productionEmployee ? $productionEmployee->name : null,
            'allocations' => $allocationsWithDetails,
            'total_quantity' => collect($allocationsWithDetails)->sum('quantity'),
        ];
    });

    $summary = [
        'total_tasks' => $formatted->count(),
        'total_quantity' => $formatted->sum('total_quantity'),
        'total_materials' => $formatted->pluck('allocations')->flatten(1)->count(),
        'confirmed_tasks' => $formatted->where('is_confirmed', true)->count(),
        'unconfirmed_tasks' => $formatted->where('is_confirmed', false)->count(),
    ];

    return response()->json([
        'message' => 'All production materials with confirmation status retrieved successfully',
        'data' => $formatted,
        'summary' => $summary
    ]);
}
public function listAllProductionMaterials(Request $request)
{
    $user = Auth::user();

    // 1. التحقق من الصلاحيات
    if (!$user->hasRole('production_employee') && !$user->hasRole('admin') && !$user->hasRole('super_admin')) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // 2. جلب جميع المهام المطلوبة (المكتملة والتي تم استلامها)
    $tasks = RawMaterialTask::where('route', 'send_to_production')
        ->whereIn('status', ['completed', 'received_by_production'])
        ->with(['user', 'admin'])
        ->orderBy('updated_at', 'desc')
        ->get();

    // 3. تنسيق البيانات (تجهيزها للفرونت إند)
    $formatted = $tasks->map(function ($task) {
        $details = $task->details ?? [];
        $sentAllocations = $details['sent_allocations'] ?? [];

        $allocationsWithDetails = [];
        foreach ($sentAllocations as $alloc) {
            $batch = RawMaterialBatch::with('rawMaterial')->find($alloc['batch_id']);
            if ($batch) {
                $allocationsWithDetails[] = [
                    'raw_material_id' => $alloc['raw_material_id'],
                    'raw_material_name' => $batch->rawMaterial->name ?? 'Unknown',
                    'batch_number' => $batch->batch_number,
                    'quantity' => $alloc['quantity'],
                    'expiry_date' => $batch->expiry_date,
                ];
            }
        }

        // تحديد الحالة للمستخدم
        $isConfirmed = ($task->status === 'received_by_production');
        $productionEmployeeName = null;
        if ($isConfirmed && !empty($details['production_received_by'])) {
            $employee = User::find($details['production_received_by']);
            $productionEmployeeName = $employee ? $employee->name : 'Unknown';
        }

        return [
            'task_id'              => $task->id,
            'status'               => $task->status, // الحالة الحقيقية (completed / received_by_production)
            'is_confirmed'         => $isConfirmed,  // Boolean سهل للاستخدام في الفرونت إند
            'sent_at'              => $task->sent_at,
            'production_received_at' => $details['production_received_at'] ?? null,
            'warehouse_keeper'     => $task->user->name ?? 'Unknown',
            'confirmed_by'         => $productionEmployeeName,
            'allocations'          => $allocationsWithDetails,
            'total_quantity'       => collect($allocationsWithDetails)->sum('quantity'),
        ];
    });

    // 4. تقسيم البيانات (عشان الفرونت إند يرتبهم بسهولة)
    return response()->json([
        'all_materials' => $formatted,
        'summary' => [
            'confirmed'   => $formatted->where('is_confirmed', true)->values(),
            'unconfirmed' => $formatted->where('is_confirmed', false)->values(),
            'total_count' => $formatted->count()
        ]
    ]);
}
}

