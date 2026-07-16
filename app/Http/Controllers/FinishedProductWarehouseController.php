<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\FinishedProductWarehouseService;
use Spatie\Permission\Traits\HasRoles;

class FinishedProductWarehouseController extends Controller
{
    protected $service;

    public function __construct(FinishedProductWarehouseService $service)
    {
        $this->service = $service;
    }

    /**
     * Authorize user check
     */
    private function authorizeUser()
    {
        $user = Auth::user();

        if (!($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('accountant') || $user->hasRole('product_storekeeper'))) {
            return false;
        }

        return true;
    }

    // Warehouse keeper lists finished product batches (FIFO order)
    public function listFinishedProductBatches(Request $request)
    {
        if (!$this->authorizeUser()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $batches = $this->service->getFinishedProductBatches();

        return response()->json(['batches' => $batches]);
    }

    // Warehouse keeper lists finished products
    public function listFinishedProducts(Request $request)
    {
        if (!$this->authorizeUser()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $products = $this->service->getFinishedProductsList();

        return response()->json(['products' => $products]);
    }

    // Display all finished products in warehouse
    public function getAllFinishedProducts(Request $request)
    {
        if (!$this->authorizeUser()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $result = $this->service->getAllFinishedProducts();

        return response()->json([
            'message' => 'All finished products retrieved successfully',
            'data' => $result['data'],
            'summary' => $result['summary']
        ], 200);
    }

    // Display product details with batch information and FIFO consideration
    public function getProductDetails($productId)
    {
        if (!$this->authorizeUser()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $result = $this->service->getProductDetails($productId);

        if (!$result) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
            'message' => 'Product details retrieved successfully',
            'product' => $result['product'],
            'batches' => $result['batches'],
        ], 200);
    }

    // Get returned items from drivers
    public function getReturnedItems(Request $request)
    {
        if (!$this->authorizeUser()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $result = $this->service->getReturnedItems();

        return response()->json([
            'message' => 'Returned items retrieved successfully',
            'data' => $result['data'],
            'summary' => $result['summary']
        ], 200);
    }

    // Accept returned item
 public function acceptReturnedItem(Request $request, $driverId)
{
    if (!$this->authorizeUser()) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }

    try {

        $result = $this->service
            ->acceptReturnedItem($driverId);

        return response()->json($result, 200);

    } catch (\Exception $e) {

        return response()->json([
            'message' => 'Failed to accept returned items',
            'error' => $e->getMessage()
        ], 400);
    }
}
    // عرض الدفعات المنتهية الصلاحية (اتلاف)
    public function expired(Request $request)
    {
        if (!$this->authorizeUser()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $result = $this->service->اتلاف();

        return response()->json([
            'message' => 'Expired batches retrieved successfully',
            'data' => $result['data'],
            'summary' => $result['summary']
        ], 200);
    }
}
