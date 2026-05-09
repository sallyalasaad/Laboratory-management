<?php

namespace App\Services;

use App\DAO\SaleDAO;
use Illuminate\Support\Facades\DB;
class SaleService
{
    private $dao;
    private $taskGuard;

    public function __construct(SaleDAO $dao, TaskTimeGuard $taskGuard)
    {
        $this->dao = $dao;
        $this->taskGuard = $taskGuard;
    }

    public function createSale($user, $storeId, $taskId)
    {
        $task = $this->dao->findTask($taskId);
        $store = $this->dao->findStore($storeId);

        $this->taskGuard->check($task);
        if ($task->status !== 'in_progress') {

            return [
                'ok' => false,
                'code' => 400,
                'message' => 'Task is not active'
            ];
        }
        if (!$task || !$store) {
            return ['ok' => false, 'code' => 404, 'message' => 'Invalid data'];
        }

        $pivot = $this->dao->getTaskStore($task, $storeId);

        if (!$pivot) {
            return ['ok' => false, 'code' => 400, 'message' => 'Store not in task'];
        }

        if ($store->type === 'retail' && !$pivot->pivot->visited) {
            return ['ok' => false, 'code' => 400, 'message' => 'Scan store first'];
        }

        $sale = $this->dao->createSale($user->id, $task->id, $store->id);

        return [
            'ok' => true,
            'data' => ['sale_id' => $sale->id]
        ];
    }

    public function allocateFromCarStock($userId, $productId, $quantity)
    {
        return DB::transaction(function () use ($userId, $productId, $quantity) {

            $items = $this->dao->getCarStockItems($userId, $productId);

            $remaining = $quantity;
            $allocations = [];

            foreach ($items as $item) {

                if ($remaining <= 0) break;

                $take = min($item->remaining_quantity, $remaining);

                $item->decrement('remaining_quantity', $take);

                $allocations[] = [
                    'car_stock_item_id' => $item->id,
                    'finished_product_batch_id' => $item->finished_product_batch_id,
                    'quantity' => $take
                ];

                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new \Exception("Not enough stock for product {$productId}");
            }

            return $allocations;
        });
    }

    public function addItems($sale, $items, $userId)
    {
        $this->taskGuard->check($sale->distributionTask);
        if (
            $sale->distributionTask->status !== 'in_progress'
        ) {

            return [
                'ok' => false,
                'message' => 'Task is not active'
            ];
        }

        if ($sale->status === 'confirmed') {
            return ['ok' => false, 'message' => 'Cannot modify confirmed sale'];
        }

        return DB::transaction(function () use ($sale, $items, $userId) {

            $total = 0;

            foreach ($items as $item) {

                $allocations = $this->allocateFromCarStock(
                    $userId,
                    $item['finished_product_id'],
                    $item['quantity']
                );

                foreach ($allocations as $alloc) {

                    $this->dao->createSaleItem([
                        'sale_id' => $sale->id,
                        'car_stock_item_id' => $alloc['car_stock_item_id'],
                        'finished_product_batch_id' => $alloc['finished_product_batch_id'],
                        'quantity' => $alloc['quantity'],
                        'price' => $item['price'],
                    ]);

                    $total += $alloc['quantity'] * $item['price'];
                }
            }

            $sale->update([
                'total_amount' => $sale->total_amount + $total
            ]);

            return [
                'ok' => true,
                'total_added' => $total
            ];
        });
    }

    public function myStock($user)
    {
        $items = \App\Models\CarStockItem::with(['batch.finishedProduct'])
            ->whereHas('carStock', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        $grouped = $items->groupBy('finished_product_id');

        return $grouped->map(function ($items, $productId) {

            $first = $items->first();

            return [
                'product_id' => $productId,
                'name' => $first->batch->finishedProduct->name ?? null,
                'size' => $first->batch->finishedProduct->size ?? null,
                'total_remaining' => $items->sum('remaining_quantity'),
                'batches' => $items->map(function ($item) {
                    return [
                        'batch_id' => $item->finished_product_batch_id,
                        'batch_number' => $item->batch->batch_number ?? null,
                        'remaining_quantity' => $item->remaining_quantity,
                    ];
                })->values()
            ];
        })->values();
    }
}
