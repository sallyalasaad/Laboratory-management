<?php

namespace App\Services;

use App\DAO\InvoiceDAO;

class InvoiceService
{
    private $dao;

    public function __construct(InvoiceDAO $dao)
    {
        $this->dao = $dao;
    }

    public function confirmSale($saleId, $user, $taskGuard, $visitService)
    {
        $sale = $this->dao->getSaleWithDetails($saleId);

        if (!$sale) {
            return ['error' => 'Sale not found', 'code' => 404];
        }

        $taskGuard->check($sale->distributionTask);
        if (
            $sale->distributionTask->status !== 'in_progress'
        ) {

            return [
                'error' => 'Task is not active',
                'code' => 400
            ];
        }

        if ($sale->status === 'confirmed') {
            return ['error' => 'Already confirmed', 'code' => 400];
        }

        if ($sale->store->type === 'wholesale' && $sale->items->isEmpty()) {
            return ['error' => 'Wholesale must have items', 'code' => 400];
        }

        return ['sale' => $sale];
    }

    public function finalizeSale($sale, $user, $visitService)
    {
        $visitService->markVisited(
            $sale->distributionTask,
            $sale->store_id
        );

        $invoice = $this->dao->createOrUpdateInvoice($sale->id, [
            'user_id' => $user->id,
            'total_amount' => $sale->total_amount,
            'date' => now()
        ]);

        $sale->update([
            'status' => 'confirmed',
            'confirmed_at' => now()
        ]);

        return $invoice;
    }

    public function getAllInvoicesData()
    {
        $sales = $this->dao->getAllSales();
        $batches = $this->dao->getBatches();

        return $sales->map(function ($sale) use ($batches) {
            return [
                'invoice_id' => $sale->id,
                'date' => $sale->date,
                'user_id' => $sale->user_id,

                'store' => [
                    'id' => $sale->store?->id,
                    'name' => $sale->store?->name,
                    'type' => $sale->store?->type,
                ],

                'total_amount' => $sale->total_amount,
                'status' => $sale->status,

                'items' => $sale->items->map(function ($item) use ($batches) {

                    $batch = $batches[$item->finished_product_batch_id] ?? null;

                    return [
                        'product_id' => $batch?->finishedProduct?->id,
                        'product_name' => $batch?->finishedProduct?->name ?? 'Unknown Product',
                        'batch_id' => $item->finished_product_batch_id,
                        'quantity' => (float) $item->quantity,
                        'price' => (float) $item->price,
                        'total' => (float) $item->quantity * (float) $item->price,
                    ];
                }),
            ];
        });
    }

    public function getInvoice($id)
    {
        return $this->dao->getInvoiceWithSale($id);
    }

    public function getDriverDailyInvoices($driverId)
    {
        $sales = $this->dao->getDriverDailySales($driverId);
        $batches = $this->dao->getBatches();

        return $sales->map(function ($sale) use ($batches) {

            return [
                'invoice_id' => $sale->id,
                'date' => $sale->date,

                'store' => [
                    'id' => $sale->store?->id,
                    'name' => $sale->store?->name,
                    'type' => $sale->store?->type,
                ],

                'total_amount' => $sale->total_amount,
                'status' => $sale->status,

                'items' => $sale->items->map(function ($item) use ($batches) {

                    $batch = $batches[$item->finished_product_batch_id] ?? null;

                    return [
                        'product_id' => $batch?->finishedProduct?->id,
                        'product_name' => $batch?->finishedProduct?->name ?? 'Unknown',
                        'quantity' => (float) $item->quantity,
                        'price' => (float) $item->price,
                        'total' => (float) $item->quantity * (float) $item->price,
                    ];
                }),
            ];
        });
    }



    public function getDriverMonthlyInvoices($driverId, $month)
    {
        $sales = $this->dao->getDriverMonthlySales($driverId, $month);
        $batches = $this->dao->getBatches();

        return $sales->map(function ($sale) use ($batches) {

            return [
                'invoice_id' => $sale->id,
                'date' => $sale->date,

                'store' => [
                    'id' => $sale->store?->id,
                    'name' => $sale->store?->name,
                ],

                'total_amount' => $sale->total_amount,
                'status' => $sale->status,

                'items' => $sale->items->map(function ($item) use ($batches) {

                    $batch = $batches[$item->finished_product_batch_id] ?? null;

                    return [
                        'product_name' => $batch?->finishedProduct?->name ?? 'Unknown',
                        'quantity' => (float) $item->quantity,
                        'total' => (float) $item->quantity * (float) $item->price,
                    ];
                }),
            ];
        });
    }



}
