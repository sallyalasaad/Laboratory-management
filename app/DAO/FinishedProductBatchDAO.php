<?php
namespace App\DAO;

use App\Models\FinishedProductBatch;

class FinishedProductBatchDAO
{
    public function create(array $data)
{
    return FinishedProductBatch::create($data);
}

    public function findByOrderId($orderId)
    {
        return FinishedProductBatch::where('production_order_id', $orderId)->get();
    }

}
