<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    //تأكيد الفاتورة
    public function confirmSale($saleId)
    {
        $sale = Sale::with(['store','distributionTask','batches'])->findOrFail($saleId);

        // ❗ منع التكرار
        if ($sale->status === 'confirmed') {
            return response()->json([
                'message' => 'Sale already confirmed'
            ], 400);
        }

        $task = $sale->distributionTask;

        // ❗ تحقق الوقت
        $now = now();
        $start = \Carbon\Carbon::parse($task->date . ' ' . $task->start_time);
        $end   = \Carbon\Carbon::parse($task->date . ' ' . $task->end_time);

        if ($now->lt($start) || $now->gt($end)) {
            return response()->json([
                'message' => 'Not allowed outside task time'
            ], 400);
        }

        DB::beginTransaction();

        try {

            // 🔥 خصم من المخزون
            foreach ($sale->batches as $batch) {

                if ($batch->quantity < $batch->pivot->quantity) {
                    return response()->json([
                        'message' => 'Not enough stock in batch ' . $batch->batch_number
                    ], 400);
                }

                $batch->decrement('quantity', $batch->pivot->quantity);
            }

            // 🔹 إنشاء الفاتورة
            Invoice::create([
                'sale_id' => $sale->id,
                'total_amount' => $sale->total_amount,
                'date' => now()
            ]);

            // 🔹 تحديث حالة البيع
            $sale->update([
                'status' => 'confirmed'
            ]);

            // 🔹 تسجيل الزيارة
            $task->stores()->updateExistingPivot($sale->store_id, [
                'visited' => true,
                'visited_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Sale confirmed successfully'
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
