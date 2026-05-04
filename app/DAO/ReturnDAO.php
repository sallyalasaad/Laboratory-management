<?php

namespace App\DAO;

use App\Models\CarStockItem;
use App\Models\FinishedProductBatch;
use App\Models\FinishedProductTask;

class ReturnDAO
{
    public function getUserCarItems($userId)
    {
        return CarStockItem::whereHas('carStock', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->where('remaining_quantity', '>', 0)
            ->lockForUpdate()
            ->get();
    }

    public function decreaseCarItem($carItem, $qty)
    {
        $carItem->decrement('remaining_quantity', $qty);
    }

    public function increaseBatch($batchId, $qty)
    {
        FinishedProductBatch::where('id', $batchId)
            ->increment('remaining_quantity', $qty);
    }

    public function getCarStock($userId)
    {
        return CarStockItem::with('batch.finishedProduct') // ✅ التعديل هنا
        ->whereHas('carStock', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();
    }
    public function getDriverStock($driverId)
    {
        return CarStockItem::whereHas('carStock', function ($q) use ($driverId) {
            $q->where('user_id', $driverId);
        })
            ->with([
                'carStock',
                'batch.finishedProduct' // 🔥 هذا هو المهم
            ])
            ->get();
    }


    public function getDriverTasks($driverId)
    {
        return FinishedProductTask::where('driver_id', $driverId)
            ->where('status', 'received')
            ->get();
    }

    public function getCarStockItems($driverId)
    {
        return CarStockItem::whereHas('carStock', function ($q) use ($driverId) {
            $q->where('user_id', $driverId);
        })
            ->get()
            ->keyBy(function ($item) {
                return $item->finished_product_batch_id;
            });
    }


}
