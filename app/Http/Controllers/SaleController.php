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


    public function addItems(Request $request, $saleId)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.car_stock_item_id' => 'required|exists:car_stock_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $sale = Sale::with('items')->find($saleId);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found'], 404);
        }

        if ($sale->status === 'confirmed') {
            return response()->json(['message' => 'Cannot modify confirmed sale'], 400);
        }

        DB::beginTransaction();

        try {
            $total = 0;

            foreach ($request->items as $item) {

                $stockItem = CarStockItem::where('id', $item['car_stock_item_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stockItem) {
                    DB::rollBack();
                    return response()->json(['message' => 'Stock item not found'], 404);
                }

                if ($stockItem->remaining_quantity < $item['quantity']) {
                    DB::rollBack();
                    return response()->json(['message' => 'Not enough stock'], 400);
                }

                // خصم المخزون
                $stockItem->decrement('remaining_quantity', $item['quantity']);

                // إضافة أو تحديث المنتج في الفاتورة
                $existingItem = SaleItem::where([
                    'sale_id' => $sale->id,
                    'car_stock_item_id' => $stockItem->id
                ])->first();

                if ($existingItem) {
                    $existingItem->quantity += $item['quantity'];
                    $existingItem->save();
                } else {
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'car_stock_item_id' => $stockItem->id,
                        'finished_product_batch_id' => $stockItem->finished_product_batch_id,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);
                }

                $total += $item['quantity'] * $item['price'];
            }

            $sale->update([
                'total_amount' => $sale->total_amount + $total
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Items added successfully',
                'total_added' => $total,
                'sale_total' => $sale->total_amount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }








}
