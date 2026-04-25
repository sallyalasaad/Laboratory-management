<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Sale;
use App\Services\VisitService;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{


    public function confirmSale($saleId,VisitService $visitService)
    {
        $sale = Sale::with(['store', 'distributionTask', 'items'])->find($saleId);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found'], 404);
        }

        if ($sale->status === 'confirmed') {
            return response()->json(['message' => 'Already confirmed'], 400);
        }

        $store = $sale->store;
        $task = $sale->distributionTask;

        DB::beginTransaction();

        try {

            // Wholesale لازم فيه منتجات
            if ($store->type === 'wholesale' && $sale->items->isEmpty()) {
                return response()->json([
                    'message' => 'Wholesale must have items'
                ], 400);
            }

            // تسجيل زيارة
            $visitService->markVisited($task, $store->id);

            // إنشاء أو تحديث الفاتورة
            Invoice::updateOrCreate(
                ['sale_id' => $sale->id],
                [
                    'user_id' => auth()->id(),
                    'total_amount' => $sale->total_amount,
                    'date' => now()
                ]
            );
            $sale->update([
                'status' => 'confirmed',
                'confirmed_at' => now()
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
