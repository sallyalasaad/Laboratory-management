<?php

namespace App\Http\Controllers;

use App\Models\CarStockItem;
use App\Models\Sale;
use App\Models\DistributionTask;
use App\Models\SaleItem;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SaleController extends Controller
{
    public function createSale(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'task_id' => 'required|exists:distribution_tasks,id',
        ]);

        $task = DistributionTask::find($request->task_id);
        app(\App\Services\TaskTimeGuard::class)->check($task);
        $store = Store::find($request->store_id);
        $user = auth()->user();

        if (!$task || !$store) {
            return response()->json(['message' => 'Invalid data'], 404);
        }

        $pivot = $task->stores()
            ->where('store_id', $store->id)
            ->first();

        if (!$pivot) {
            return response()->json(['message' => 'Store not in task'], 400);
        }

        // Retail لازم scan
        if ($store->type === 'retail' && !$pivot->pivot->visited) {
            return response()->json(['message' => 'Scan store first'], 400);
        }

        $sale = Sale::firstOrCreate(
            [
                'store_id' => $store->id,
                'distribution_task_id' => $task->id,
                'user_id' => $user->id,
                'status' => 'draft'
            ],
            [
                'date' => now(),
                'total_amount' => 0
            ]
        );

        return response()->json([
            'sale_id' => $sale->id
        ]);
    }

    public function allocateFromCarStock($userId, $productId, $quantity)
    {
        return DB::transaction(function () use ($userId, $productId, $quantity) {

            $items = CarStockItem::whereHas('carStock', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
                ->where('finished_product_id', $productId)
                ->where('remaining_quantity', '>', 0)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

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

    public function addItems(Request $request, $saleId)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.finished_product_id' => 'required|exists:finished_products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $sale = Sale::findOrFail($saleId);

// ✅ IMPORTANT: منع أي إضافة خارج وقت المهمة
        $task = $sale->distributionTask;
        app(\App\Services\TaskTimeGuard::class)->check($task);

        if ($sale->status === 'confirmed') {
            return response()->json(['message' => 'Cannot modify confirmed sale'], 400);
        }

        DB::beginTransaction();

        try {
            $total = 0;

            foreach ($request->items as $item) {

                // 🔥 FIFO من السيارة
                $allocations = $this->allocateFromCarStock(
                    auth()->id(),
                    $item['finished_product_id'],
                    $item['quantity']
                );

                foreach ($allocations as $alloc) {

                    SaleItem::create([
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

            DB::commit();

            return response()->json([
                'message' => 'Items added successfully',
                'total_added' => $total
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

///عرض المنتجات مع السائق
    public function myStock()
    {
        $user = auth()->user();

        $items = CarStockItem::with([
            'batch.finishedProduct'
        ])
            ->whereHas('carStock', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        // 🔥 grouping حسب المنتج
        $grouped = $items->groupBy('finished_product_id');

        $result = $grouped->map(function ($items, $productId) {

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

        return response()->json([
            'data' => $result
        ]);
    }


}
