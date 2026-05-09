<?php

namespace App\DAO;

use App\Models\Sale;
use App\Models\Invoice;
use App\Models\FinishedProductBatch;

class InvoiceDAO
{
    public function getSaleWithDetails($saleId)
    {
        return Sale::with(['store', 'distributionTask', 'items'])->find($saleId);
    }

    public function createOrUpdateInvoice($saleId, $data)
    {
        return Invoice::updateOrCreate(
            ['sale_id' => $saleId],
            $data
        );
    }

    public function getAllSales()
    {
        return Sale::with(['store', 'items'])
            ->orderByDesc('id')
            ->get();
    }

    public function getBatches()
    {
        return FinishedProductBatch::with('finishedProduct')
            ->get()
            ->keyBy('id');
    }

    public function getInvoiceWithSale($id)
    {
        return Invoice::with(['sale.store', 'sale.items'])
            ->find($id);
    }

    public function getDriverDailySales($driverId)
    {
        return Sale::with(['store', 'items'])
            ->where('user_id', $driverId)
            ->whereDate('date', now()->toDateString())
            ->get();
    }

    public function getDriverMonthlySales($driverId, $month)
    {
        return Sale::with(['store', 'items'])
            ->where('user_id', $driverId)
            ->whereMonth('date', $month)
            ->get();
    }






}
