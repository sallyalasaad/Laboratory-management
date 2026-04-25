<?php

namespace App\Http\Controllers;

use App\Models\DistributionTask;
use App\Models\Store;
use App\Services\VisitService;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function scanStore(Request $request,VisitService $visitService)
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

        return response()->json([
            'store_id' => $store->id,
            'store_type' => $store->type,
            'must_sell' => $store->type === 'wholesale'
        ]);
    }
}
