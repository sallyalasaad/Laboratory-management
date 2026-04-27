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

        // ❌ منع البدء بدون مواد
        if ($status === 'in_progress') {

            $hasMaterials = \App\Models\RawMaterialTask::where('production_order_id', $orderId)
                ->where('status', 'received_by_production')
                ->exists();

            if (!$hasMaterials) {
                throw new \Exception("لا يمكن بدء الإنتاج: لا توجد مواد مستلمة من المستودع");
            }
        }

        return $this->dao->updateStatus($order, $status);
    }public function getCurrentTasks()
{
    $orders = $this->dao->getCurrentOrders()
        ->whereIn('status', [
            'materials_received',
            'in_progress',
            'paused'
        ]);

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
                    'id' => $stage->id,
                    'stage_name' => $stage->stage_name,
                    'status' => $stage->status
                ];
            }),

            'note' => $order->note,
        ];
    });
}
    public function getOrdersHistory()
    {
        $orders = $this->dao->listAll();

        return $orders->map(function ($order) {
            return [
                'task_number' => $order->order_number,
                'product_name' => $order->product?->name,
                'product_size' => $order->product?->size,
                'product_unit' => $order->product?->unit,
                'quantity' => $order->quantity,
                'status' => $order->status,
                'date' => $order->created_at->format('Y-m-d'),
                'note' => $order->note,
            ];
        });
    }
    public function getIncomingTasks()
    {
        $orders = $this->dao->getIncomingOrders()
            ->whereIn('status', ['pending','accepted']);

        return $orders->map(function ($order) {
            return [
                'task_number' => $order->order_number,
                'product_name' => $order->product?->name,
                'product_size' => $order->product?->size,
                'product_unit' => $order->product?->unit,
                'quantity' => $order->quantity,
                'date' => $order->created_at->format('Y-m-d'),
                'status' => $order->status,
                'note' => $order->note,
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
