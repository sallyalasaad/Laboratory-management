<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    // 🔥 دالة موحدة
    private function markVisited($task, $storeId)
    {
        $storePivot = $task->stores()
            ->where('store_id', $storeId)
            ->first();

        if ($storePivot && !$storePivot->pivot->visited) {
            $task->stores()->updateExistingPivot($storeId, [
                'visited' => true,
                'visited_at' => now(),
            ]);
        }
    }

    public function confirmSale($saleId)
    {
        $sale = Sale::with(['store','distributionTask','items'])->findOrFail($saleId);

        if ($sale->status === 'confirmed') {
            return response()->json(['message' => 'Already confirmed'], 400);
        }

        $task = $sale->distributionTask;

        if ($task->user_id != auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {

            // إنشاء الفاتورة
            Invoice::firstOrCreate(
                ['sale_id' => $sale->id],
                [
                    'total_amount' => $sale->total_amount,
                    'date' => now(),
                    'user_id' => auth()->id()
                ]
            );

            // تأكيد البيع
            $sale->update([
                'status' => 'confirmed',
                'confirmed_at' => now()
            ]);

            // 🔥 تسجيل الزيارة النهائي (للنوعين)
            $this->markVisited($task, $sale->store_id);

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
