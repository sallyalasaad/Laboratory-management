<?php

namespace App\Http\Controllers;

use App\Models\DistributionTask;
use App\Models\Sale;
use App\Models\Store;
use App\Services\VisitService;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function scanStore(Request $request, VisitService $visitService)
    {
        $request->validate([
            'barcode' => 'required'
        ]);

        $user = auth()->user();

        $store = Store::where('barcode', $request->barcode)->first();

        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $task = DistributionTask::where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->where('status', 'in_progress')
            ->with('region')
            ->first();

        if (!$task) {
            return response()->json(['message' => 'No active task'], 404);
        }

        $storePivot = $task->stores()
            ->where('store_id', $store->id)
            ->first();

        if (!$storePivot) {
            return response()->json(['message' => 'Store not in your task'], 400);
        }

        // Retail → تسجيل زيارة فقط
        if ($store->type === 'retail') {
            $visitService->markVisited($task, $store->id);
        }

        // 🔥 جلب أو إنشاء الفاتورة (draft)
        $sale = Sale::firstOrCreate(
            [
                'store_id' => $store->id,
                'distribution_task_id' => $task->id,
                'user_id' => $user->id,
                'status' => 'draft'
            ],
            [
                'date' => now(),
                'total_amount' => 0
            ]
        );

        return response()->json([
            // ================= STORE =================
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'type' => $store->type, // 🔥 أهم شيء
                'barcode' => $store->barcode,
                'lat' => $store->lat,
                'lng' => $store->lng,
            ],

            // ================= TASK =================
            'task' => [
                'id' => $task->id,
                'date' => $task->date,
                'region' => $task->region->name,
                'status' => $task->status,
            ],

            // ================= INVOICE (FIXED STRUCTURE) =================
            'invoice' => [
                'id' => $sale->id,
                'status' => $sale->status,
                'date' => $sale->date,
                'total_amount' => $sale->total_amount,
                'items_count' => $sale->items()->count(),
            ],

            // ================= RULES =================
            'rules' => [
                'must_sell' => $store->type === 'wholesale',
                'allow_add_items' => true,
                'require_scan' => true
            ]
        ]);
    }
}
