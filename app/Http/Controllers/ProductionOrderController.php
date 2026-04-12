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

    // 🟢 إرسال ملاحظة (موظف أو أدمن)
    public function sendNote(Request $request)
    {
        $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'message' => 'required|string'
        ]);

        $user = auth()->user();

        $note = Note::create([
            'from_user_id' => $user->id,
            'to_user_id' => $request->to_user_id,
            'message' => $request->message,
            'is_read' => false,
            'production_order_id' => null // ملاحظة عامة
        ]);

        return response()->json([
            'message' => 'تم إرسال الملاحظة',
            'note' => $note
        ]);
    }

    // 🟢 عرض كل الملاحظات (Inbox + Sent)
    public function notes()
    {
        $user = auth()->user();

        $notes = Note::with('fromUser')
            ->where(function ($q) use ($user) {
                $q->where('to_user_id', $user->id)
                    ->orWhere('from_user_id', $user->id);
            })
            ->whereNull('production_order_id') // فقط العامة
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notes);
    }

    // 🟢 عدد غير المقروء
    public function unreadNotes()
    {
        $count = Note::where('to_user_id', auth()->id())
            ->where('is_read', false)
            ->whereNull('production_order_id')
            ->count();

        return response()->json(['count' => $count]);
    }

    // 🟢 تعليم كمقروءة
    public function markAsRead($id)
    {
        $note = Note::where('id', $id)
            ->where('to_user_id', auth()->id())
            ->firstOrFail();

        $note->update(['is_read' => true]);

        return response()->json(['message' => 'تم القراءة']);
    }

    // 🟢 حذف ملاحظة
    public function deleteNote($id)
    {
        $note = Note::where(function ($q) {
            $q->where('from_user_id', auth()->id())
                ->orWhere('to_user_id', auth()->id());
        })
            ->findOrFail($id);

        $note->delete();

        return response()->json(['message' => 'تم حذف الملاحظة']);
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
