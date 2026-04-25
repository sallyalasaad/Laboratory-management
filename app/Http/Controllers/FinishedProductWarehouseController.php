<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\FinishedProductBatch;
use App\Models\FinishedProduct;
use Carbon\Carbon;
use Spatie\Permission\Traits\HasRoles;

class FinishedProductWarehouseController extends Controller
{
    // Warehouse keeper lists finished product batches (FIFO order)
    public function listFinishedProductBatches(Request $request)
    {
        $user = Auth::user();

        // Assuming warehouse keeper has a specific role, e.g., 'product_storekeeper'
        // For now, allow admin or super_admin, or add role check
        if (!($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('product_storekeeper'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $batches = FinishedProductBatch::with(['finishedProduct', 'productionOrder'])
            ->orderBy('production_date', 'asc') // FIFO: earliest production date first
            ->get();

        $formatted = $batches->map(function ($batch) {
            return [
                'id' => $batch->id,
                'finished_product_name' => $batch->finishedProduct->name ?? 'Unknown',
                'batch_number' => $batch->batch_number,
                'quantity' => $batch->quantity,
                'remaining_quantity' => $batch->quantity, // Assuming full quantity if no tracking
                'production_date' => $batch->production_date,
                'expiry_date' => $batch->expiry_date,
                'received_from_production_at' => $batch->production_date, // Assuming production_date is when received
            ];
        });

        return response()->json(['batches' => $formatted]);
    }

    // Warehouse keeper lists finished products
    public function listFinishedProducts(Request $request)
    {
        $user = Auth::user();

        if (!($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('product_storekeeper'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $products = FinishedProduct::with('batches')->get();

        $formatted = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
                //'description' => $product->description,
                'total_batches' => $product->batches->count(),
                'total_quantity' => $product->batches->sum('quantity'),
            ];
        });

        return response()->json(['products' => $formatted]);
    }

    // Display all finished products in warehouse (شاملة)
    public function getAllFinishedProducts(Request $request)
    {
        $user = Auth::user();

        if (!($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('product_storekeeper'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get all products with their batches
        $products = FinishedProduct::with(['batches' => function ($query) {
            $query->where('remaining_quantity', '>', 0)
                  ->orderBy('production_date', 'asc'); // FIFO order
        }])->get();

        $formatted = $products->map(function ($product) {
            $batches = $product->batches;

            if ($batches->isEmpty()) {
                return null;
            }

            $batchDetails = $batches->map(function ($batch) {
                $expiryDate = Carbon::parse($batch->expiry_date);
                $isExpired = $expiryDate->isPast();
                $daysUntilExpiry = $expiryDate->diffInDays(Carbon::now());

                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $batch->quantity,
                    'remaining_quantity' => $batch->remaining_quantity,
                    'production_date' => $batch->production_date,
                    'expiry_date' => $batch->expiry_date,
                    'received_from_production_at' => $batch->production_date,
                    'days_until_expiry' => $isExpired ? 0 : $daysUntilExpiry,
                    'is_expired' => $isExpired,
                    'status' => $isExpired ? 'منتهية الصلاحية' : ($daysUntilExpiry < 30 ? 'قريبة من الانتهاء' : 'صالحة'),
                ];
            });

            return [
                'id' => $product->id,
                'name' => $product->name,
                'size' => $product->size,
                'unit' => $product->unit,
                'description' => $product->description,
                'total_quantity' => $batches->sum('quantity'),
                'total_remaining_quantity' => $batches->sum('remaining_quantity'),
                'total_batches' => $batches->count(),
                'batches' => $batchDetails,
            ];
        })->filter(function ($product) {
            return $product !== null;
        })->values();

        return response()->json([
            'message' => 'All finished products retrieved successfully',
            'data' => $formatted,
            'summary' => [
                'total_products' => $formatted->count(),
                'total_quantity' => $formatted->sum('total_quantity'),
                'total_remaining_quantity' => $formatted->sum('total_remaining_quantity'),
                'total_batches' => $formatted->sum('total_batches'),
            ]
        ], 200);
    }

    // Display finished products by description
    public function getProductsByDescription(Request $request)
    {
        $user = Auth::user();

        if (!($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('product_storekeeper'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $description = $request->query('description');

        $query = FinishedProduct::query();

        // Filter by description if provided
        if ($description) {
            $query->where('description', 'like', '%' . $description . '%');
        }

        $products = $query->with(['batches' => function ($batch) {
            $batch->where('remaining_quantity', '>', 0)
                  ->orderBy('production_date', 'asc'); // FIFO order
        }])->get();

        $formatted = $products->map(function ($product) {
            if ($product->batches->isEmpty()) {
                return null;
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'size' => $product->size,
                'unit' => $product->unit,
                'description' => $product->description,
                'total_batches' => $product->batches->count(),
                'total_remaining_quantity' => $product->batches->sum('remaining_quantity'),
            ];
        })->filter(function ($product) {
            return $product !== null;
        })->values();

        return response()->json([
            'message' => 'Products retrieved successfully',
            'data' => $formatted,
            'count' => $formatted->count()
        ], 200);
    }

    // Display product details with batch information and FIFO consideration
    public function getProductDetails($productId)
    {
        $user = Auth::user();

        if (!($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('product_storekeeper'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $product = FinishedProduct::find($productId);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Get all batches ordered by production date (FIFO)
        $batches = FinishedProductBatch::where('finished_product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('production_date', 'asc')
            ->get();

        $batchDetails = $batches->map(function ($batch) {
            $expiryDate = Carbon::parse($batch->expiry_date);
            $isExpired = $expiryDate->isPast();
            $daysUntilExpiry = $expiryDate->diffInDays(Carbon::now());

            return [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $batch->quantity,
                'remaining_quantity' => $batch->remaining_quantity,
                'production_date' => $batch->production_date,
                'expiry_date' => $batch->expiry_date,
                'received_from_production_at' => $batch->production_date,
                'days_until_expiry' => $isExpired ? 0 : $daysUntilExpiry,
                'is_expired' => $isExpired,
                'status' => $isExpired ? 'منتهية الصلاحية' : ($daysUntilExpiry < 30 ? 'قريبة من الانتهاء' : 'صالحة'),
            ];
        });

        $totalQuantity = $batches->sum('quantity');
        $totalRemainingQuantity = $batches->sum('remaining_quantity');

        return response()->json([
            'message' => 'Product details retrieved successfully',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'size' => $product->size,
                'unit' => $product->unit,
                'description' => $product->description,
                'total_quantity' => $totalQuantity,
                'total_remaining_quantity' => $totalRemainingQuantity,
                'total_batches' => $batches->count(),
            ],
            'batches' => $batchDetails,
        ], 200);
    }
}
