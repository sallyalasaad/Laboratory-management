<?php
namespace App\DAO;

use App\Models\ProductionOrder;

class ProductionOrderDAO
{
    public function create(array $data)
    {
        return ProductionOrder::create($data);
    }

    public function findById($id)
    {
        return ProductionOrder::find($id);
    }

    public function updateStatus(ProductionOrder $order, $status)
    {
        $order->status = $status;
        $order->save();
        return $order;
    }

    public function listAll()
    {
        return ProductionOrder::with('stages')->get();
    }
}
