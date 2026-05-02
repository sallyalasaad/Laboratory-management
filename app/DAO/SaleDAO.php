<?php


namespace App\DAO;

use App\Models\CarStockItem;
use App\Models\Sale;
use App\Models\SaleItem;

class SaleDAO
{
    public function findTask($taskId)
    {
        return \App\Models\DistributionTask::find($taskId);
    }

    public function findStore($storeId)
    {
        return \App\Models\Store::find($storeId);
    }

    public function getTaskStore($task, $storeId)
    {
        return $task->stores()
            ->where('store_id', $storeId)
            ->first();
    }

    public function createSale($userId, $taskId, $storeId)
    {
        return Sale::firstOrCreate(
            [
                'store_id' => $storeId,
                'distribution_task_id' => $taskId,
                'user_id' => $userId,
                'status' => 'draft'
            ],
            [
                'date' => now(),
                'total_amount' => 0
            ]
        );
    }

    public function getCarStockItems($userId, $productId)
    {
        return CarStockItem::whereHas('carStock', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->where('finished_product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();
    }

    public function createSaleItem($data)
    {
        return SaleItem::create($data);
    }
}
