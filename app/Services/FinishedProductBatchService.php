<?php

namespace App\Services;

use App\DAO\FinishedProductBatchDAO;
use Illuminate\Support\Str;

class FinishedProductBatchService
{
    protected $dao;

    public function __construct(FinishedProductBatchDAO $dao)
    {
        $this->dao = $dao;
    }

    public function createBatch($finishedProductId, $productionOrderId, $quantity, $productionDate)
    {
        return $this->dao->create([
            'finished_product_id' => $finishedProductId,
            'production_order_id' => $productionOrderId,

            // ✅ توليد تلقائي للـ batch number
            'batch_number' => 'BATCH-' . now()->format('Ymd') . '-' . Str::random(4),

            'quantity' => $quantity,

            // 🔥 أهم شيء (لازم تبقى)
            'remaining_quantity' => $quantity,

            'production_date' => $productionDate
        ]);
    }

    public function getBatchesByOrder($orderId)
    {
        return $this->dao->findByOrderId($orderId);
    }
}
