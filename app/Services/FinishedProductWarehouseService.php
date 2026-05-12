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
            $query->orderBy('production_date', 'asc');
        }])->get();

        $formatted = $products->map(function ($product) {
            $batches = $product->batches;

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
        });

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
                'size' => $product->size,
                'unit' => $product->unit,
                'total_batches' => $product->batches->count(),
                'total_quantity' => $product->batches->sum('quantity'),
            ];
        });
    }

    /**
     * Get returned items from drivers
     */
    public function getReturnedItems()
    {
        // Get all returned items from CarStockItem where quantity > 0
        // These represent items that were sent to drivers and returned
        $returnedItems = \App\Models\CarStockItem::with([
            'finishedProductBatch.finishedProduct',
            'carStock.user' // driver info
        ])
        ->where('quantity', '>', 0)
        ->whereHas('carStock', function($query) {
            $query->where('status', 'active');
        })
        ->orderBy('created_at', 'desc')
        ->get();

        $formattedReturns = $returnedItems->map(function ($item) {
            $batch = $item->finishedProductBatch;
            $driver = $item->carStock->user;

            // Find the original send task for this batch and driver
           $sendTask = \App\Models\FinishedProductTask::where('driver_id', $driver->id)
    ->where('status', 'sent')
    ->whereRaw(
        "JSON_CONTAINS(JSON_EXTRACT(details, '$.allocations'), JSON_OBJECT('batch_id', ?))",
        [$item->finished_product_batch_id]
    )
    ->orderBy('sent_at', 'desc')
    ->first();
            return [
                'id' => $item->id,
                'send_task_id' => $sendTask ? $sendTask->id : null,
                'finished_product_name' => $batch->finishedProduct->name ?? 'Unknown',
                'size' => $batch->finishedProduct->size ?? '',
                'returned_quantity' => $item->quantity,
                'production_date' => $batch->production_date,
                'expiry_date' => $batch->expiry_date,
                'driver_name' => $driver->name,
                'send_date' => $sendTask ? $sendTask->sent_at : null,
                'return_date' => $item->updated_at, // When it was returned
                'batch_number' => $batch->batch_number,
            ];
        });

        return [
            'data' => $formattedReturns,
            'summary' => [
                'total_returns' => $formattedReturns->count(),
                'total_quantity_returned' => $formattedReturns->sum('returned_quantity'),
            ]
        ];
    }

    /**
     * Accept returned item and update warehouse stock
     */public function acceptReturnedItem($driverId)
{
    $returnedItems = \App\Models\CarStockItem::with([
        'finishedProductBatch.finishedProduct',
        'carStock.user'
    ])
    ->where('quantity', '>', 0)
    ->whereHas('carStock', function ($q) use ($driverId) {
        $q->where('user_id', $driverId);
    })
    ->get();

    if ($returnedItems->isEmpty()) {
        throw new \Exception("No returned items found for this driver");
    }

    $acceptedItems = [];
    $totalQty = 0;

    \Illuminate\Support\Facades\DB::transaction(function () use (
        $returnedItems,
        &$acceptedItems,
        &$totalQty
    ) {

        foreach ($returnedItems as $item) {

            $batch = $item->finishedProductBatch;

            $batch->increment(
                'remaining_quantity',
                $item->quantity
            );

            $acceptedItems[] = [
                'product_name' => $batch->finishedProduct->name ?? '',
                'size' => $batch->finishedProduct->size ?? '',
                'batch_number' => $batch->batch_number,
                'accepted_quantity' => $item->quantity,
                'production_date' => $batch->production_date,
                'expiry_date' => $batch->expiry_date,
                'driver_name' => $item->carStock->user->name ?? ''
            ];

            $totalQty += $item->quantity;

            $item->update([
                'quantity' => 0
            ]);
        }
    });

    return [
        'message' => 'All returned items accepted successfully',
        'accepted_items_count' => count($acceptedItems),
        'accepted_total_quantity' => $totalQty,
        'items' => $acceptedItems
    ];
}
}
