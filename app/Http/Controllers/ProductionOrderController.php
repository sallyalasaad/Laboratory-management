<?php
namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\ProductionOrder;
use App\Models\RawMaterialBatch;
use App\Models\RawMaterialTask;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\ProductionOrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductionOrderController extends Controller
{
    protected $service;

    public function __construct(ProductionOrderService $service)
    {
        $this->service = $service;
    }

    public function create(Request $request)
    {
        $request->validate([
         
            'user_id' => 'required|integer|exists:users,id',
            'finished_product_id' => 'required|integer|exists:finished_products,id',
            'quantity' => 'required|numeric|min:0.01',
            'note' => 'nullable|string'
        ]);

        $order = $this->service->createOrder(
            $request->user_id,
            $request->finished_product_id,
            $request->quantity,
            $request->note
        );

        return response()->json([
            'message' => 'Production order created',
            'order' => $order
        ], 201);
    }
    public function currentTasks()
    {
        return response()->json(
            $this->service->getCurrentTasks()
        );
    }

    public function ordersHistory()
    {
        return response()->json(
            $this->service->getOrdersHistory()
        );
    }
    public function incomingTasks()
    {
        return response()->json(
            $this->service->getIncomingTasks()
        );
    }
    public function addProductionNote(Request $request)
    {
        $request->validate([
            'production_order_id' => 'required|exists:production_orders,id',
            'message' => 'required|string'
        ]);

        $user = auth()->user();

        $note = Note::create([
            'production_order_id' => $request->production_order_id,
            'from_user_id' => $user->id,
            'to_user_id' => null, // أو حدد موظف
            'message' => $request->message,
            'is_read' => false
        ]);

        return response()->json([
            'message' => 'تمت إضافة الملاحظة',
            'note' => $note
        ]);
    }

    ////🙂
    public function addGeneralNote(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $user = auth()->user();

        // جلب الأدمن
        $admin = User::role('admin')->first();

        if (!$admin) {
            return response()->json(['message' => 'Admin not found'], 404);
        }

        // إنشاء الملاحظة بدون ربط بـ production_order_id
        $note = Note::create([
            'production_order_id' => null,
            'from_user_id' => $user->id,
            'to_user_id' => $admin->id,
            'message' => $request->message,
            'is_read' => false
        ]);

        return response()->json([
            'message' => 'تمت إضافة الملاحظة العامة بنجاح',
            'note' => $note
        ]);
    }



















    // 🟢 6. عرض ملاحظات طلب
    public function getProductionNotes($orderId)
    {
        $notes = Note::with('fromUser')
            ->where('production_order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notes);
    }

    // 🟢 7. تعليم كمقروءة
    public function markAsRead($id)
    {
        $note = Note::findOrFail($id);
        $note->is_read = true;
        $note->save();

        return response()->json([
            'message' => 'تم قراءة الملاحظة'
        ]);
    }

    // 🟢 8. عدد غير المقروء
    public function getUnreadCount($orderId)
    {
        $count = Note::where('production_order_id', $orderId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'count' => $count
        ]);
    }

    // 🟢 9. حذف ملاحظة (اختياري)
    public function deleteNote($id)
    {
        $note = Note::findOrFail($id);
        $note->delete();

        return response()->json([
            'message' => 'تم حذف الملاحظة'
        ]);
    }
    // 🟢 كل الطلبات
    public function allOrders()
    {
        return response()->json($this->service->getAllOrders());
    }

// 🟢 طلب واحد
    public function show($id)
    {
        return response()->json($this->service->getSingleOrder($id));
    }

    ////
    public function confirmReceipt(Request $request, $taskId)
    {
        $user = Auth::user();

        $task = RawMaterialTask::where('id', $taskId)
            ->where('user_id', $user->id)
            ->where('route', 'receive')
            ->firstOrFail();

        $items = $request->input('items'); // مصفوفة من المواد مع الكمية والوحدة

        if (empty($items)) {
            return response()->json(['message' => 'يرجى إدخال المواد المراد استلامها'], 422);
        }

        $receivedBatches = [];

        DB::transaction(function() use ($items, $task, &$receivedBatches) {
            foreach ($items as $item) {
                $batch = RawMaterialBatch::create([
                    'raw_material_name' => $item['name'], // مثال: حليب بقري
                    'unit' => $item['unit'], // مثال: لتر
                    'quantity' => $item['quantity'],
                    'remaining_quantity' => $item['quantity'],
                    'received_at' => now(),
                ]);

                $receivedBatches[] = $batch->toArray();
            }

            $task->status = 'received';
            $task->received_at = now();
            $task->details['received_batches'] = $receivedBatches;
            $task->save();
        });

        return response()->json([
            'message' => 'تم تأكيد استلام المواد بنجاح',
            'received_batches' => $receivedBatches
        ]);
    }







}
