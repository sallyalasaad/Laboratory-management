<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Sale;
use App\Services\VisitService;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{

    public function confirmSale($saleId, VisitService $visitService)
    {
        $sale = Sale::with(['store', 'distributionTask', 'items'])->find($saleId);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found'], 404);
        }

        if ($sale->status === 'confirmed') {
            return response()->json(['message' => 'Already confirmed'], 400);
        }

        // ❗ validation خارج transaction
        if ($sale->store->type === 'wholesale' && $sale->items->isEmpty()) {
            return response()->json([
                'message' => 'Wholesale must have items'
            ], 400);
        }

        DB::beginTransaction();

        try {

            // تسجيل زيارة
            $visitService->markVisited(
                $sale->distributionTask,
                $sale->store_id
            );

            // إنشاء الفاتورة
            Invoice::updateOrCreate(
                ['sale_id' => $sale->id],
                [
                    'user_id' => request()->user()->id,
                    'total_amount' => $sale->total_amount,
                    'date' => now()
                ]
            );

            // تحديث البيع
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
///عرض الفواتير
    public function index()
    {
        $invoices = \App\Models\Sale::with(['store', 'items'])
            ->orderByDesc('id')
            ->get();

        // 🔥 تحميل كل الـ batches مع المنتجات مرة واحدة لتفادي N+1
        $batches = \App\Models\FinishedProductBatch::with('finishedProduct')
            ->get()
            ->keyBy('id');

        $data = $invoices->map(function ($sale) use ($batches) {

            return [
                'invoice_id' => $sale->id,
                'date' => $sale->date,
                'user_id' => $sale->user_id,

                'store' => [
                    'id' => $sale->store?->id,
                    'name' => $sale->store?->name,
                    'type' => $sale->store?->type,
                ],

                'total_amount' => $sale->total_amount,
                'status' => $sale->status,

                'items' => $sale->items->map(function ($item) use ($batches) {

                    $batch = $batches[$item->finished_product_batch_id] ?? null;

                    return [
                        'product_id' => $batch?->finishedProduct?->id,
                        'product_name' => $batch?->finishedProduct?->name ?? 'Unknown Product',
                        'batch_id' => $item->finished_product_batch_id,
                        'quantity' => (float) $item->quantity,
                        'price' => (float) $item->price,
                        'total' => (float) $item->quantity * (float) $item->price,
                    ];
                }),
            ];
        });

        return response()->json([
            'data' => $data
        ]);
    }
///عرض فاتورة واحدة
    public function show($id)
    {
        $invoice = Invoice::with([
            'sale.store',
            'sale.items'
        ])->find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        return response()->json([
            'invoice_id' => $invoice->id,
            'date' => $invoice->date,
            'total_amount' => (float) $invoice->total_amount,

            'store' => [
                'name' => $invoice->sale->store->name,
                'type' => $invoice->sale->store->type,
            ],

            'items' => $invoice->sale->items->map(function ($item) {

                // نجيب الباتش مباشرة بدون علاقة batch داخل SaleItem
                $batch = \App\Models\FinishedProductBatch::with('finishedProduct')
                    ->find($item->finished_product_batch_id);

                return [
                    'product_id' => $batch?->finished_product_id,
                    'product_name' => $batch?->finishedProduct?->name ?? 'Unknown Product',

                    'batch_id' => $item->finished_product_batch_id,
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) ($item->quantity * $item->price),
                ];
            })
        ]);
    }
}
