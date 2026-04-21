<?php
namespace App\Services;

use App\DAO\ProductionOrderDAO;
use App\Models\ProductionStage;
use Illuminate\Support\Facades\DB;

class ProductionOrderService
{
    protected $dao;

    public function __construct(ProductionOrderDAO $dao)
    {
        $this->dao = $dao;
    }


    public function createOrder($userId, $finishedProductId, $quantity, $note = null)
    {
        return DB::transaction(function () use ($userId, $finishedProductId, $quantity, $note) {
            $order = $this->dao->create([
                'user_id' => $userId,
                'finished_product_id' => $finishedProductId,
                'quantity' => $quantity,
                'status' => 'pending', // الطلب يبدأ كـ "معلق"
                'note' => $note
            ]);

            $stages = ['تحضير', 'طبخ', 'تبريد', 'إرسال للمستودع'];
            foreach ($stages as $stage) {
                ProductionStage::create([
                    'production_order_id' => $order->id,
                    'stage_name' => $stage,
                    'status' => 'pending' // جميع المراحل مبدئيًا معلقة
                ]);
            }

            $order->load('product');

            return [
                'order' => $order,
                'product_name' => $order->product?->name,
                'product_size' => $order->product?->size,
                'product_unit' => $order->product?->unit
            ];
        });
    }
    public function getOrderWithStages($orderId)
    {
        return $this->dao->findById($orderId)->load('stages');
    }

    public function updateStatus($orderId, $status)
    {
        $order = $this->dao->findById($orderId);
        return $this->dao->updateStatus($order, $status);
    }
    public function getCurrentTasks()
    {
        $orders = $this->dao->getCurrentOrders()
            ->whereIn('status', ['in_progress']);

        return $orders->map(function ($order) {
            return [
                'task_number' => $order->order_number,
                'status' => $order->status,

                'product_name' => $order->product?->name,
                'product_size' => $order->product?->size,
                'product_unit' => $order->product?->unit,

                'quantity' => $order->quantity,
                'date' => $order->created_at->format('Y-m-d'),

                'stages' => $order->stages->map(function ($stage) {
    return [
        'id' => $stage->id, // ✅ إضافة ID
        'stage_name' => $stage->stage_name,
        'status' => $stage->status
    ];
}),

                // ✅ هذا اللي بدك ياه
                'note' => $order->note,
            ];
        });
    }

    public function getOrdersHistory()
    {
        $orders = $this->dao->getOrdersHistory();

        return $orders->map(function ($order) {
        return [
            'task_number' => $order->order_number,
            'product_name' => $order->product?->name,
            'product_size' => $order->product?->size,
            'product_unit' => $order->product?->unit,
            'quantity' => $order->quantity,
            'status' => $order->status,
            'date' => $order->created_at->format('Y-m-d'),

            // ✅ عرض الملاحظة نفسها
            'note' => $order->note,
        ];
    });
    }
    public function getIncomingTasks()
    {
        $orders = $this->dao->getIncomingOrders();

        return $orders->map(function ($order) {
            return [
                'task_number' => $order->order_number,
                'product_name' => $order->product->name,
                'product_size' => $order->product->size,  // أضف هذا
                'product_unit' => $order->product->unit,  // أضف هذا
                'quantity' => $order->quantity,
                'date' => $order->created_at->format('Y-m-d'),
                'status' => $order->status
            ];
        });
    }
    public function getAllOrders()
    {
        return $this->dao->listAll();
    }
    public function getSingleOrder($id)
    {
        $order = $this->dao->findById($id)
            ->load(['product','stages','notes.fromUser']);
        return [
            'order' => $order,
            'product_name' => $order->product?->name,
            'product_size' => $order->product?->size,  // جديد
            'product_unit' => $order->product?->unit   // جديد
        ];
    }
}
