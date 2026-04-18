<?php

namespace App\Http\Controllers;

use App\Models\CarStock;
use App\Models\CarStockItem;
use App\Models\FinishedProductTask;
use Illuminate\Http\Request;
use App\Services\FinishedProductReceiveService;
use Illuminate\Support\Facades\DB;

class FinishedProductReceiveController extends Controller
{
    protected $service;

    public function __construct(FinishedProductReceiveService $service)
    {
        $this->service = $service;
    }

    public function confirmReceive($taskId, Request $request)
    {
        $task = FinishedProductTask::where('id', $taskId)
            ->where('driver_id', $request->user()->id)
            ->firstOrFail();

        if ($task->status !== 'sent') {
            return response()->json(['message' => 'Not sent yet'], 400);
        }

        DB::beginTransaction();

        try {

            // 1) جلب أو إنشاء مخزون السيارة
            $carStock = CarStock::firstOrCreate(
                [
                    'user_id' => $task->driver_id,
                    'status' => 'active'
                ],
                [
                    'distribution_task_id' => null
                ]
            );

            // 2) إضافة المنتجات من allocations
            foreach ($task->details['allocations'] as $item) {

                $existing = CarStockItem::where('car_stock_id', $carStock->id)
                    ->where('finished_product_id', $item['finished_product_id'])
                    ->where('finished_product_batch_id', $item['batch_id'])
                    ->first();

                if ($existing) {
                    $existing->increment('quantity', $item['quantity']);
                    $existing->increment('remaining_quantity', $item['quantity']);
                } else {
                    CarStockItem::create([
                        'car_stock_id' => $carStock->id,
                        'finished_product_id' => $item['finished_product_id'],
                        'finished_product_batch_id' => $item['batch_id'],
                        'quantity' => $item['quantity'],
                        'remaining_quantity' => $item['quantity'],
                    ]);
                }
            }

            // 3) تحديث الحالة
            $task->update([
                'status' => 'received'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Car stock updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
