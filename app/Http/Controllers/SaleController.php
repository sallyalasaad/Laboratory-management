<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    //إنشاء بيع (مسودة بدون تأكيد)
    public function createSale(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'task_id' => 'required|exists:distribution_tasks,id',
        ]);

        $sale = Sale::create([
            'store_id' => $request->store_id,
            'distribution_task_id' => $request->task_id,
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
            'items.*.batch_id' => 'required|exists:finished_product_batches,id',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $sale = Sale::findOrFail($saleId);

        $total = $sale->total_amount;

        foreach ($request->items as $item) {

            $batch = \App\Models\FinishedProductBatch::findOrFail($item['batch_id']);

            // ❗ تحقق من توفر الكمية
            if ($batch->quantity < $item['quantity']) {
                return response()->json([
                    'message' => 'Not enough stock in batch ' . $batch->batch_number
                ], 400);
            }

            // 🔹 إضافة للفاتورة
            $sale->batches()->attach($item['batch_id'], [
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ]);

            // 🔹 خصم من المخزون
            $batch->decrement('quantity', $item['quantity']);

            // 🔹 حساب المجموع
            $total += $item['quantity'] * $item['price'];
        }

        $sale->update(['total_amount' => $total]);

        return response()->json([
            'total' => $total
        ]);
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
    }
}
