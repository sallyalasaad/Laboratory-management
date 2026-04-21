<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\DistributionTask;
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

        $user = auth()->user();

        $task = DistributionTask::findOrFail($request->task_id);

        // 🔵 Retail → لازم زيارة مسبقة
        if ($task->type === 'retail') {

            $storePivot = $task->stores()
                ->where('store_id', $request->store_id)
                ->first();

            if (!$storePivot || !$storePivot->pivot->visited) {
                return response()->json([
                    'message' => 'You must scan store first'
                ], 400);
            }
        }

        // 🔴 Wholesale → لازم scan قبل البيع
        if ($task->type === 'wholesale') {

            $storePivot = $task->stores()
                ->where('store_id', $request->store_id)
                ->first();

            if (!$storePivot || !$storePivot->pivot->scanned_at) {
                return response()->json([
                    'message' => 'You must scan store first'
                ], 400);
            }
        }

        $sale = Sale::create([
            'store_id' => $request->store_id,
            'distribution_task_id' => $task->id,
            'user_id' => $user->id,
            'date' => now(),
            'total_amount' => 0
        ]);

        return response()->json([
            'sale_id' => $sale->id
        ]);
    }



    //إضافة منتجات على البيع
    public function addItems(Request $request, $saleId)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.car_stock_item_id' => 'required|exists:car_stock_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $sale = Sale::findOrFail($saleId);
        $user = auth()->user();

        if ($sale->status === 'confirmed') {
            return response()->json([
                'message' => 'Cannot modify confirmed sale'
            ], 400);
        }

        DB::beginTransaction();

        try {

            $total = $sale->total_amount;

            foreach ($request->items as $item) {

                $stockItem = \App\Models\CarStockItem::where('id', $item['car_stock_item_id'])
                    ->lockForUpdate()
                    ->first();

                if ($stockItem->carStock->user_id != $user->id) {
                    DB::rollBack();
                    return response()->json(['message' => 'Unauthorized stock item'], 403);
                }

                if ($stockItem->remaining_quantity < $item['quantity']) {
                    DB::rollBack();
                    return response()->json(['message' => 'Not enough stock'], 400);
                }

                $stockItem->decrement('remaining_quantity', $item['quantity']);

                $existingItem = \App\Models\SaleItem::where('sale_id', $sale->id)
                    ->where('car_stock_item_id', $stockItem->id)
                    ->first();

                if ($existingItem) {
                    $existingItem->increment('quantity', $item['quantity']);
                } else {
                    \App\Models\SaleItem::create([
                        'sale_id' => $sale->id,
                        'car_stock_item_id' => $stockItem->id,
                        'finished_product_batch_id' => $stockItem->finished_product_batch_id,
                        'quantity' => $item['quantity'],
                        'price' => $item['price']
                    ]);
                }

                $total += $item['quantity'] * $item['price'];
            }

            $sale->update(['total_amount' => $total]);

            DB::commit();

            return response()->json([
                'message' => 'Items added successfully',
                'total' => $total
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function receiveStock(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.batch_id' => 'required|exists:finished_product_batches,id',
            'items.*.quantity' => 'required|numeric|min:1'
        ]);

        $user = auth()->user();

        foreach ($request->items as $item) {

            $batch = \App\Models\FinishedProductBatch::findOrFail($item['batch_id']);

            if ($batch->quantity < $item['quantity']) {
                return response()->json([
                    'message' => 'الكمية غير كافية في المستودع'
                ], 400);
            }

            // 🔻 خصم من المستودع
            $batch->decrement('quantity', $item['quantity']);

            // 🔺 إضافة لمخزون السيارة
            \App\Models\CarStock::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'batch_id' => $batch->id
                ],
                [
                    'quantity' => DB::raw('quantity + ' . $item['quantity'])
                ]
            );
        }

        return response()->json([
            'message' => 'تم استلام البضاعة بنجاح'
        ]);

}}
