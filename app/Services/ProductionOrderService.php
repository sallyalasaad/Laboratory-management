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
                'status' => 'pending',
                'note' => $note
            ]);

            $stages = ['تحضير', 'طبخ', 'تعبئة', 'تبريد', 'إنهاء'];

            foreach ($stages as $index => $stage) {
                ProductionStage::create([
                    'production_order_id' => $order->id,
                    'stage_name' => $stage,
                    'status' => $index === 0 ? 'active' : 'pending'
                ]);
            }

            return $order;
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
    public function listOrders()
    {
        return $this->dao->listAll(); // هذا داخلي داخل Service
    }

}
