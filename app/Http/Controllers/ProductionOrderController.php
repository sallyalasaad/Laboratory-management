<?php
namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\ProductionOrder;
use Illuminate\Http\Request;
use App\Services\ProductionOrderService;
use Illuminate\Support\Facades\Auth;

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
}
