<?php

namespace App\DAO;

use App\Models\DistributionTask;
use App\Models\Sale;
use App\Models\Store;

class StoreDAO
{
    public function findStoreByBarcode($barcode)
    {
        return Store::where('barcode', $barcode)->first();
    }
    public function getActiveTask($userId)
    {
        return DistributionTask::where('user_id', $userId)

            ->whereDate('date', now()->toDateString())

            ->where('status', 'in_progress')

            ->with('region')

            ->orderBy('start_time')

            ->first();
    }

    public function getTaskStore($task, $storeId)
    {
        return $task->stores()
            ->where('store_id', $storeId)
            ->first();
    }

    public function createOrGetSale($userId, $taskId, $storeId)
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
}
