<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProductionOrderService;
use Illuminate\Support\Facades\Auth;

class ProductionOrderController extends Controller
{
    protected $service;

    public function __construct(ProductionOrderService $service)
    {
        $this->service = $service;
    }

    public function create(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'finished_product_id' => 'required|integer|exists:finished_products,id',
            'quantity' => 'required|numeric|min:0.01',
            'batch_number' => 'required|string',
            'note' => 'nullable|string'
        ]);

        $order = $this->service->createOrder(
            $request->user_id,
            $request->finished_product_id,
            $request->quantity,
            $request->batch_number,
            $request->note
        );

        return response()->json(['message' => 'Production order created', 'order' => $order], 201);
    }
    public function listOrders()
    {
        $orders = $this->service->listOrders();
        return response()->json($orders);
    }

}
