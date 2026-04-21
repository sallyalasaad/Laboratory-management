<?php

namespace App\Http\Controllers;

use App\Models\DistributionTask;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    // 🔥 دالة موحدة لتسجيل الزيارة
    private function markVisited($task, $storeId)
    {
        $storePivot = $task->stores()
            ->where('store_id', $storeId)
            ->first();

        if ($storePivot && !$storePivot->pivot->visited) {
            $task->stores()->updateExistingPivot($storeId, [
                'visited' => true,
                'visited_at' => now(),
            ]);
        }
    }

    // 📌 scan (يستخدم فقط بالمفرق)
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
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', ['in_progress'])
            ->first();

        if (!$task) {
            return response()->json(['message' => 'No active task'], 400);
        }

        $storePivot = $task->stores()
            ->where('store_id', $store->id)
            ->first();

        if (!$storePivot) {
            return response()->json(['message' => 'Store not in your task'], 400);
        }

        // 🔵 Retail → تسجيل زيارة مباشرة
        if ($task->type === 'retail') {
            $this->markVisited($task, $store->id);
        }

        // 🔴 Wholesale → تسجيل scan فقط
        if ($task->type === 'wholesale') {
            $task->stores()->updateExistingPivot($store->id, [
                'scanned_at' => now()
            ]);
        }

        return response()->json([
            'store_id' => $store->id,
            'store_name' => $store->name,
            'task_id' => $task->id,
            'task_type' => $task->type,
            'scanned' => true
        ]);
    }
}
