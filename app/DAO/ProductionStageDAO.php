<?php
namespace App\DAO;

use App\Models\ProductionStage;

class ProductionStageDAO
{
    public function findById($id)
    {
        return ProductionStage::find($id);
    }

    public function findByOrderId($orderId)
    {
        return ProductionStage::where('production_order_id', $orderId)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function updateStatus(ProductionStage $stage, $status)
    {
        $stage->status = $status;
        $stage->save();
        return $stage;
    }

    public function getActiveStage($orderId)
    {
        return ProductionStage::where('production_order_id', $orderId)
            ->where('status', 'active')
            ->first();
    }
}
