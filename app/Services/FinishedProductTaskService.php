<?php

namespace App\Services;

use App\Models\FinishedProductTask;
use App\Models\FinishedProductBatch;
use Illuminate\Support\Facades\DB;

class FinishedProductTaskService
{
    public function createSendTask($adminId, $userId, $driverId, $items)
    {
        // Check availability for each item
        foreach ($items as $item) {
            $finishedProductId = $item['finished_product_id'];
            $requestedQuantity = $item['quantity'];

            $availableQuantity = FinishedProductBatch::where('finished_product_id', $finishedProductId)->sum('remaining_quantity');

            if ($availableQuantity < $requestedQuantity) {
                throw new \Exception("Insufficient quantity for finished product ID {$finishedProductId}. Available: {$availableQuantity}, Requested: {$requestedQuantity}");
            }
        }

        $task = FinishedProductTask::create([
            'user_id' => $userId,
            'driver_id' => $driverId,
            'route' => 'send_to_market',
            'status' => 'pending',
            'details' => [
                'driver_id' => $driverId,
                'items' => $items
            ]
        ]);

        return $task;
    }

    public function allocateFifo($finishedProductId, $quantity)
    {
        $batches = FinishedProductBatch::where('finished_product_id', $finishedProductId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('production_date', 'asc')
            ->lockForUpdate()
            ->get();

        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $available = $batch->remaining_quantity;
            $take = min($available, $remaining);

            $allocations[] = [
                'batch_id' => $batch->id,
                'quantity' => $take
            ];

            $remaining -= $take;
        }

        return $allocations;
    }

    public function confirmSend($taskId)
    {
        $task = FinishedProductTask::findOrFail($taskId);

        if ($task->status !== 'pending') {
            throw new \Exception('Task already processed');
        }

        $items = $task->details['items'] ?? [];
        $createdAllocations = [];

        DB::transaction(function () use ($items, $task, &$createdAllocations) {

            foreach ($items as $item) {

                $finishedProductId = $item['finished_product_id'];
                $qty = $item['quantity'];

                $allocs = $this->allocateFifo($finishedProductId, $qty);

                $allocatedQty = array_sum(array_map(fn($a) => $a['quantity'], $allocs));

                if ($allocatedQty < $qty) {
                    throw new \Exception("Insufficient stock for product {$finishedProductId}");
                }

                foreach ($allocs as $alloc) {

                    $batch = FinishedProductBatch::find($alloc['batch_id']);

                    // ✅ خصم من المستودع (مرة واحدة فقط)
                    $batch->remaining_quantity -= $alloc['quantity'];
                    $batch->save();

                    $createdAllocations[] = [
                        'finished_product_id' => $finishedProductId,
                        'batch_id' => $alloc['batch_id'],
                        'quantity' => $alloc['quantity']
                    ];
                }
            }

            // ✅ تحديث المهمة
            $task->update([
                'status' => 'sent',
                'sent_at' => now(),
                'details' => [
                    'allocations' => $createdAllocations
                ]
            ]);
        });

        return $createdAllocations;
    }

    public function getProductionEmployeeTasks($userId = null)
    {
        $query = FinishedProductTask::with(['user', 'driver']);

        // إذا كان المستخدم موظف إنتاج، عرض مهامه فقط
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
