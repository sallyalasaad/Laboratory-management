<?php

namespace App\Services;

use App\Models\FinishedProductBatch;
use App\Models\FinishedProduct;
use Carbon\Carbon;

class FinishedProductWarehouseService
{
    /**
     * Get all finished products with their batches
     */
    public function getAllFinishedProducts()
    {
        $products = FinishedProduct::with(['batches' => function ($query) {
            $query->where('remaining_quantity', '>', 0)
                  ->orderBy('production_date', 'asc');
        }])->get();

        $formatted = $products->map(function ($product) {
            $batches = $product->batches;

            if ($batches->isEmpty()) {
                return null;
            }

            $batchDetails = $batches->map(function ($batch) {
                return $this->formatBatchDetails($batch);
            });

            return [
                'id' => $product->id,
                'name' => $product->name,
                'size' => $product->size,
                'unit' => $product->unit,
                'total_quantity' => $batches->sum('quantity'),
                'total_remaining_quantity' => $batches->sum('remaining_quantity'),
                'total_batches' => $batches->count(),
                'batches' => $batchDetails,
            ];
        })->filter(function ($product) {
            return $product !== null;
        })->values();

        return [
            'data' => $formatted,
            'summary' => [
                'total_products' => $formatted->count(),
                'total_quantity' => $formatted->sum('total_quantity'),
                'total_remaining_quantity' => $formatted->sum('total_remaining_quantity'),
                'total_batches' => $formatted->sum('total_batches'),
            ]
        ];
    }

    /**
     * Get product details by ID
     */
    public function getProductDetails($productId)
    {
        $product = FinishedProduct::find($productId);

        if (!$product) {
            return null;
        }

        $batches = FinishedProductBatch::where('finished_product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('production_date', 'asc')
            ->get();

        $batchDetails = $batches->map(function ($batch) {
            return $this->formatBatchDetails($batch);
        });

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'size' => $product->size,
                'unit' => $product->unit,
                'total_quantity' => $batches->sum('quantity'),
                'total_remaining_quantity' => $batches->sum('remaining_quantity'),
                'total_batches' => $batches->count(),
            ],
            'batches' => $batchDetails,
        ];
    }

    /**
     * Format batch details with expiry information
     */
    private function formatBatchDetails($batch)
    {
        $expiryDate = Carbon::parse($batch->expiry_date);
        $isExpired = $expiryDate->isPast();
        $daysUntilExpiry = $expiryDate->diffInDays(Carbon::now());

        return [
            'id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'quantity' => $batch->quantity,
            'remaining_quantity' => $batch->remaining_quantity,
            'production_date' => $batch->production_date,
            'expiry_date' => $batch->expiry_date,
            'received_from_production_at' => $batch->production_date,
            'days_until_expiry' => $isExpired ? 0 : $daysUntilExpiry,
            'is_expired' => $isExpired,
            'status' => $isExpired ? 'منتهية الصلاحية' : ($daysUntilExpiry < 30 ? 'قريبة من الانتهاء' : 'صالحة'),
        ];
    }

    /**
     * Get finished products batches in FIFO order
     */
    public function getFinishedProductBatches()
    {
        $batches = FinishedProductBatch::with(['finishedProduct', 'productionOrder'])
            ->orderBy('production_date', 'asc')
            ->get();

        return $batches->map(function ($batch) {
            return [
                'id' => $batch->id,
                'finished_product_name' => $batch->finishedProduct->name ?? 'Unknown',
                'batch_number' => $batch->batch_number,
                'quantity' => $batch->quantity,
                'remaining_quantity' => $batch->quantity,
                'production_date' => $batch->production_date,
                'expiry_date' => $batch->expiry_date,
                'received_from_production_at' => $batch->production_date,
            ];
        });
    }

    /**
     * Get finished products list
     */
    public function getFinishedProductsList()
    {
        $products = FinishedProduct::with('batches')->get();

        return $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
                'total_batches' => $product->batches->count(),
                'total_quantity' => $product->batches->sum('quantity'),
            ];
        });
    }
}
