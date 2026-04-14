<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\FinishedProductBatch;
use App\Models\FinishedProduct;
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
                'description' => $product->description,
                'total_batches' => $product->batches->count(),
                'total_quantity' => $product->batches->sum('quantity'),
            ];
        });

        return response()->json(['products' => $formatted]);
    }
}
