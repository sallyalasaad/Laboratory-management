<?php

namespace App\Http\Controllers;

use App\Models\DistributionTask;
use App\Models\Store;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\FinishedProductBatch;
use Illuminate\Support\Facades\DB;
class StoreController extends Controller
{
    //قراءة الباركود (تجيب المحل + تتحقق من المهمة)

    public function scanStore(Request $request)
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
            ->whereDate('date', now())
            ->whereIn('status', ['in_progress'])
            ->first();

        if (!$task) {
            return response()->json(['message' => 'No active task'], 400);
        }

        $exists = $task->stores()->where('store_id', $store->id)->exists();

        if (!$exists) {
            return response()->json(['message' => 'Store not in your task'], 400);
        }

        // 🔥 إنشاء sale تلقائي إذا ما موجود
        $sale = \App\Models\Sale::firstOrCreate([
            'store_id' => $store->id,
            'distribution_task_id' => $task->id,
        ], [
            'total_amount' => 0,
            'status' => 'pending'
        ]);

        return response()->json([
            'store' => $store,
            'task_id' => $task->id,
            'sale_id' => $sale->id
        ]);
    }
}
