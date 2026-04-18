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
        $sale = Sale::with(['store','distributionTask','items'])->findOrFail($saleId);

        if ($sale->status === 'confirmed') {
            return response()->json([
                'message' => 'Sale already confirmed'
            ], 400);
        }

        $task = $sale->distributionTask;

        if ($task->user_id != auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

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

            // ❗ منع تكرار الفاتورة
            $invoice = Invoice::firstOrCreate(
                ['sale_id' => $sale->id],
                [
                    'total_amount' => $sale->total_amount,
                    'date' => now()
                ]
            );

            $sale->update([
                'status' => 'confirmed',
                'confirmed_at' => now()
            ]);

            // ❗ منع تكرار الزيارة
            $storePivot = $task->stores()->where('store_id', $sale->store_id)->first();

            if ($storePivot && $storePivot->pivot->visited) {
                return response()->json([
                    'message' => 'Store already visited'
                ], 400);
            }

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
