<?php
namespace App\DAO;

use App\Models\FinishedProductBatch;
// بدلاً من السطر الخاطئ، اجعليه هكذا:
use App\Models\FinishedProduct;
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

public function updateStatus($id, string $status, array$additionalData = [])
{
    $batch = FinishedProductBatch::findOrFail($id);
    
    $batch->status =$status;
    
    if (!empty($additionalData)) {
        $batch->fill($additionalData);
    }
    
    $batch->save();
    return $batch;
}


    public function getTasksForReceive()
{
    return FinishedProductBatch::whereIn('status', ['sent', 'received'])
        ->orderBy('created_at', 'desc')
        ->get();
}

}
