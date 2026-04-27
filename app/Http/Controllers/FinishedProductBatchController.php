<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FinishedProductBatchService;

class FinishedProductBatchController extends Controller
{
    protected $service;

    public function __construct(FinishedProductBatchService $service)
    {
        $this->service = $service;
    }
    public function create(Request $request)
    {
        $request->validate([
            'finished_product_id' => 'required|integer|exists:finished_products,id',
            'production_order_id' => 'required|integer|exists:production_orders,id',
            'quantity' => 'required|numeric|min:0.01',
            'production_date' => 'required|date'
        ]);

        $batch = $this->service->createBatch(
            $request->finished_product_id,
            $request->production_order_id,
            $request->quantity,
            $request->production_date
        );

        return response()->json([
            'message' => 'Batch created',
            'batch' => $batch
        ], 201);
    }

    public function list1($orderId)
    {
        return response()->json(
            $this->service->getBatchesByOrder($orderId)
        );
    }
}
