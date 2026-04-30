<?php

namespace App\Services;

use App\Models\FinishedProductTask;
use App\Models\FinishedProductBatch;
use Illuminate\Support\Facades\DB;

class FinishedProductTaskService
{public function createSendTask($adminId, $userId, $driverId, $items)
{
    return DB::transaction(function () use ($userId, $driverId, $items) {

        foreach ($items as $item) {

            $available = FinishedProductBatch::where('finished_product_id', $item['finished_product_id'])
                ->sum('remaining_quantity');

            if ($available < $item['quantity']) {
                throw new \Exception(
                    "Not enough stock for product {$item['finished_product_id']}. Available: {$available}"
                );
            }
        }

        return FinishedProductTask::create([
            'user_id' => $userId,
            'driver_id' => $driverId,
            'route' => 'send_to_market',
            'status' => 'pending',
            'details' => [
                'items' => $items
            ]
        ]);
    });
}
    // ✅ FIFO allocation
    public function allocateFifo($finishedProductId, $quantity)
    {
        $allocations = [];
        $remaining = $quantity;

        $batches = FinishedProductBatch::where('finished_product_id', $finishedProductId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('production_date', 'asc')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {

            if ($remaining <= 0) break;

            $take = min($batch->remaining_quantity, $remaining);

            $updated = FinishedProductBatch::where('id', $batch->id)
                ->where('remaining_quantity', '>=', $take)
                ->decrement('remaining_quantity', $take);

            if (!$updated) {
                throw new \Exception("Concurrency error on batch {$batch->id}");
            }

            $allocations[] = [
                'batch_id' => $batch->id,
                'quantity' => $take
            ];

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new \Exception("Not enough stock during allocation");
        }

        return $allocations;
    }


    public function confirmSend($taskId, $storekeeperId)
    {
        $task = FinishedProductTask::findOrFail($taskId);

        if ($task->user_id != $storekeeperId) {
            throw new \Exception('Unauthorized');
        }

        if ($task->status !== 'pending') {
            throw new \Exception('Task already processed');
        }

        return DB::transaction(function () use ($task) {

            $items = $task->details['items'] ?? [];
            $allocations = [];

            foreach ($items as $item) {

                $allocs = $this->allocateFifo(
                    $item['finished_product_id'],
                    $item['quantity']
                );

                foreach ($allocs as $alloc) {
                    $allocations[] = [
                        'finished_product_id' => $item['finished_product_id'],
                        'batch_id' => $alloc['batch_id'],
                        'quantity' => $alloc['quantity']
                    ];
                }
            }

            $task->update([
                'status' => 'sent',
                'sent_at' => now(),
                'details' => [
                    'items' => $items,
                    'allocations' => $allocations
                ]
            ]);

            return $allocations;
        });
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
