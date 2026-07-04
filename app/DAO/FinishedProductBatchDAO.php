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
// دالة تحديث الحالة
    public function updateStatus($id, string $status, array $additionalData = [])
    {
        $batch = FinishedProductBatch::findOrFail($id);
        $batch->status = $status;
        
        // تحديث أي بيانات إضافية (مثل تفعيل الكمية عند الاستلام)
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
