<?php

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use App\Services\TaskTimeGuard;
use App\Services\VisitService;

class InvoiceController extends Controller
{
    private $service;
    private $taskGuard;

    public function __construct(InvoiceService $service, TaskTimeGuard $taskGuard)
    {
        $this->service = $service;
        $this->taskGuard = $taskGuard;
    }


    public function confirmSale($saleId, VisitService $visitService)
    {
        $result = $this->service->confirmSale(
            $saleId,
            request()->user(),
            $this->taskGuard,
            $visitService
        );

        if (isset($result['error'])) {
            return response()->json(
                ['message' => $result['error']],
                $result['code']
            );
        }

        DB::beginTransaction();

        try {
            $this->service->finalizeSale(
                $result['sale'],
                request()->user(),
                $visitService
            );

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

    public function index()
    {
        return response()->json([
            'data' => $this->service->getAllInvoicesData()
        ]);
    }

    public function show($id)
    {
        $invoice = $this->service->getInvoice($id);

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
