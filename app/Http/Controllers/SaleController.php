<?php

namespace App\Http\Controllers;

use App\Models\CarStockItem;
use App\Models\Sale;
use App\Models\DistributionTask;
use App\Models\SaleItem;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SaleController extends Controller
{
    public function createSale(Request $request, \App\Services\SaleService $service)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'task_id' => 'required|exists:distribution_tasks,id',
        ]);

        $result = $service->createSale(
            auth()->user(),
            $request->store_id,
            $request->task_id
        );

        if (!$result['ok']) {
            return response()->json([
                'message' => $result['message']
            ], $result['code']);
        }

        return response()->json($result['data']);
    }
    public function addItems(Request $request, $saleId, \App\Services\SaleService $service)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.finished_product_id' => 'required|exists:finished_products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $sale = \App\Models\Sale::findOrFail($saleId);

        $result = $service->addItems(
            $sale,
            $request->items,
            auth()->id()
        );

        if (!$result['ok']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => 'Items added successfully',
            'total_added' => $result['total_added']
        ]);
    }
    public function myStock(\App\Services\SaleService $service)
    {
        return response()->json([
            'data' => $service->myStock(auth()->user())
        ]);
    }




}
