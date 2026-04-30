<?php

namespace App\Services;

use App\DAO\FinishedProductBatchDAO;
use App\Models\FinishedProductBatch;
use App\Models\ProductionOrder;
use Illuminate\Support\Str;
use App\Exceptions\InsufficientQuantityException;
class FinishedProductBatchService
{
    protected $dao;

    public function __construct(FinishedProductBatchDAO $dao)
    {
        $this->dao = $dao;
    }
    public function createBatch($finishedProductId, $productionOrderId, $quantity, $productionDate)
    {
        $order = ProductionOrder::findOrFail($productionOrderId);

        $produced = FinishedProductBatch::where('production_order_id', $productionOrderId)
            ->sum('quantity');

        $remaining = $order->quantity - $produced;

        if ($remaining <= 0) {
            throw new InsufficientQuantityException("تم إنتاج كامل الكمية بالفعل");
        }

        if ($quantity > $remaining) {
            throw new InsufficientQuantityException("الكمية المتبقية فقط: $remaining");
        }

        $batch = $this->dao->create([
            'finished_product_id' => $finishedProductId,
            'production_order_id' => $productionOrderId,
            'batch_number' => 'BATCH-' . now()->format('Ymd') . '-' . Str::random(4),
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'production_date' => $productionDate
        ]);

        if ($produced + $quantity >= $order->quantity) {
            $order->status = 'completed';
        } else {
            $order->status = 'in_progress';
        }

        $order->save();

        return $batch;
    }


    public function getBatchesByOrder($orderId)
    {
        return $this->dao->findByOrderId($orderId);
    }
}
