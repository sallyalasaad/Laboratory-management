<?php
namespace App\Services;

use App\DAO\FinishedProductBatchDAO;

class FinishedProductBatchService
{
    protected $dao;

    public function __construct(FinishedProductBatchDAO $dao)
    {
        $this->dao = $dao;
    }

    public function createBatch($finishedProductId, $productionOrderId, $batchNumber, $quantity, $productionDate)
    {
        return $this->dao->create([
            'finished_product_id'=>$finishedProductId,
            'production_order_id'=>$productionOrderId,
            'batch_number'=>$batchNumber,
            'quantity'=>$quantity,
             'remaining_quantity' => $quantity,
            'production_date'=>$productionDate
        ]);
    }

    public function getBatchesByOrder($orderId)
    {
        return $this->dao->findByOrderId($orderId);
    }
}
