<?php

namespace App\Services;

use App\Models\FinishedProductTask;
use App\Models\CarStock;
use App\Models\CarStockItem;
use Illuminate\Support\Facades\DB;

class FinishedProductReceiveService
{
    public function confirmReceive($taskId)
    {
        $task = FinishedProductTask::findOrFail($taskId);

        // ✅ لازم تكون مرسلة
        if ($task->status !== 'sent') {
            throw new \Exception("Task not ready for receive");
        }

        $allocations = $task->details['allocations'] ?? [];

        DB::transaction(function () use ($task, $allocations) {

            // ✅ إنشاء أو جلب car stock
            $carStock = CarStock::firstOrCreate(
                [
                    'user_id' => $task->driver_id,
                    'status' => 'active'
                ]
            );

            foreach ($allocations as $alloc) {

                CarStockItem::updateOrCreate(
                    [
                        'car_stock_id' => $carStock->id,
                        'finished_product_batch_id' => $alloc['batch_id']
                    ],
                    [
                        'finished_product_id' => $alloc['finished_product_id'],
                        'quantity' => DB::raw("quantity + {$alloc['quantity']}"),
                        'remaining_quantity' => DB::raw("remaining_quantity + {$alloc['quantity']}")
                    ]
                );
            }

            // ✅ تحديث حالة المهمة
            $task->update([
                'status' => 'received',
                'received_at' => now()
            ]);
        });

        return true;
    }
}
