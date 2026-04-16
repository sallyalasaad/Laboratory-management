<?php

namespace App\Services;

use App\Models\FinishedProductTask;
use App\Models\FinishedProductBatch;
use App\Models\CarStock;
use App\Models\CarStockItem;
use Illuminate\Support\Facades\DB;

class FinishedProductReceiveService
{
    public function confirmReceive($taskId)
    {
        $task = FinishedProductTask::findOrFail($taskId);

        if ($task->status !== 'pending') {
            throw new \Exception("Task already processed");
        }

        $items = $task->details['items'] ?? [];

        DB::transaction(function () use ($task, $items) {

            // 1) جهز car stock للسائق
            $carStock = CarStock::firstOrCreate(
                [
                    'user_id' => $task->driver_id,
                    'distribution_task_id' => null,
                    'status' => 'active'
                ]
            );

            foreach ($items as $item) {

                $productId = $item['finished_product_id'];
                $qtyNeeded = $item['quantity'];

                // 2) جلب الباتشات FIFO
                $batches = FinishedProductBatch::where('finished_product_id', $productId)
                    ->where('remaining_quantity', '>', 0)
                    ->orderBy('production_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $remaining = $qtyNeeded;

                foreach ($batches as $batch) {

                    if ($remaining <= 0) break;

                    $take = min($batch->remaining_quantity, $remaining);

                    if ($take <= 0) continue;

                    // 3) خصم من warehouse
                    $batch->remaining_quantity -= $take;
                    $batch->save();

                    // 4) إضافة إلى car stock
                    CarStockItem::updateOrCreate(
                        [
                            'car_stock_id' => $carStock->id,
                            'finished_product_batch_id' => $batch->id
                        ],
                        [
                            'finished_product_id' => $productId,
                            'quantity' => DB::raw("quantity + $take"),
                            'remaining_quantity' => DB::raw("remaining_quantity + $take")
                        ]
                    );

                    $remaining -= $take;
                }

                if ($remaining > 0) {
                    throw new \Exception("Not enough stock for product ID: $productId");
                }
            }

            // 5) تحديث حالة المهمة
            $task->status = 'received';
            $task->sent_at = now();
            $task->save();
        });

        return true;
    }
}
