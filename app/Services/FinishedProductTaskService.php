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
            'admin_id' => $adminId,
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

        if ($task->status === 'completed') {
            throw new \Exception('Task already completed');
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
                    throw new \Exception("Insufficient inventory for finished product id {$finishedProductId}: requested {$qty}, allocated {$allocatedQty}");
                }

                foreach ($allocs as $alloc) {
                    $batch = FinishedProductBatch::find($alloc['batch_id']);
                    $batch->remaining_quantity -= $alloc['quantity'];
                    $batch->save();

                    $createdAllocations[] = [
                        'finished_product_id' => $finishedProductId,
                        'batch_id' => $alloc['batch_id'],
                        'quantity' => $alloc['quantity']
                    ];
                }
            }

            $task->status = 'completed';
            $task->sent_at = now();
            $details = $task->details ?? [];
            $details['sent_allocations'] = $createdAllocations;
            unset($details['items']);
            $task->details = $details;
            $task->save();
        });

        return $createdAllocations;
    }
}
