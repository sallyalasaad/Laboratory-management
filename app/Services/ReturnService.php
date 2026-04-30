<?php

namespace App\Services;

use App\DAO\ReturnDAO;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    protected $dao;

    public function __construct(ReturnDAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 🔥 Auto return all remaining stock from car to warehouse
     */
    public function autoReturn($userId)
    {
        return DB::transaction(function () use ($userId) {

            $items = $this->dao->getUserCarItems($userId);

            if ($items->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No stock to return'
                ];
            }

            foreach ($items as $item) {

                $qty = $item->remaining_quantity;

                if ($qty <= 0) {
                    continue;
                }

                // 🔁 رجّع للمستودع نفس الدفعة
                $this->dao->increaseBatch(
                    $item->finished_product_batch_id,
                    $qty
                );

                // 🚚 صفّر السيارة
                $this->dao->decreaseCarItem($item, $qty);
            }

            return [
                'success' => true,
                'message' => 'All stock returned successfully'
            ];
        });
    }
    public function getCarStock($userId)
    {
        $items = $this->dao->getCarStock($userId);

        return [
            'driver_id' => $userId,
            'stock' => $items->map(function ($item) {
                return [
                    'car_stock_item_id' => $item->id,
                    'finished_product_id' => $item->finished_product_id,
                    'batch_id' => $item->finished_product_batch_id,

                    // ✅ أضف هذا السطر
                    'product_name' => $item->batch?->finishedProduct?->name,

                    'quantity' => (float) $item->quantity,
                    'remaining_quantity' => (float) $item->remaining_quantity,
                ];
            })
        ];
    }



    public function getDriverStock($driverId)
    {
        $stock = $this->dao->getDriverStock($driverId);

        return $stock->map(function ($item) {
            return [
                'car_stock_item_id' => $item->id,
                'finished_product_id' => $item->finished_product_id,
                'batch_id' => $item->finished_product_batch_id,
                'quantity' => $item->quantity,
                'remaining_quantity' => $item->remaining_quantity,
            ];
        });
    }


    public function getDriverReport($driverId)
    {
        $tasks = $this->dao->getDriverTasks($driverId);
        $carItems = $this->dao->getCarStockItems($driverId);

        // 🔥 جمع المبيعات مرة واحدة (أسرع)
        $soldMap = SaleItem::selectRaw('car_stock_item_id, SUM(quantity) as total_sold')
            ->groupBy('car_stock_item_id')
            ->pluck('total_sold', 'car_stock_item_id');

        $report = [];

        foreach ($tasks as $task) {

            foreach ($task->details['allocations'] ?? [] as $alloc) {

                $batchId = $alloc['batch_id'];
                $receivedQty = $alloc['quantity'];

                $carItem = $carItems[$batchId] ?? null;

                $remaining = $carItem->remaining_quantity ?? 0;

                // ✅ المبيعات من sale_items
                $sold = $carItem
                    ? ($soldMap[$carItem->id] ?? 0)
                    : 0;

                // ✅ المرتجع الحقيقي
                $returned = $receivedQty - $sold - $remaining;

                if ($returned < 0) {
                    $returned = 0;
                }

                $report[] = [
                    'finished_product_id' => $alloc['finished_product_id'],
                    'batch_id' => $batchId,

                    // 📥
                    'received_quantity' => (float) $receivedQty,

                    // 🛒
                    'sold_quantity' => (float) $sold,

                    // 🚚
                    'remaining_in_car' => (float) $remaining,

                    // 🔁
                    'returned_to_warehouse' => (float) $returned,
                ];
            }
        }

        return $report;
    }


}
