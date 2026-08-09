<?php

namespace App\Services;

use App\DAO\FinishedProductBatchDAO;
use App\Models\FinishedProductBatch;
use App\Models\ProductionOrder;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Exceptions\InsufficientQuantityException;
use App\Models\ProductionStage;
use App\Services\ProductionStageService;
use Illuminate\Support\Facades\DB;
class FinishedProductBatchService
{
    protected $dao;
    protected $stageService; // تم إضافة المتغير هنا

    // تم إضافة ProductionStageService للحقن في الـ Constructor
    public function __construct(FinishedProductBatchDAO $dao, ProductionStageService $stageService)
    {
        $this->dao = $dao;
        $this->stageService = $stageService;
    }

    public function getBatchesByOrder($orderId)
    {
        return $this->dao->findByOrderId($orderId);
    }

public function createBatch($finishedProductId, $productionOrderId, $quantity, $productionDate, $expiryType, $expiryValue)
    {
        // نضع العملية داخل Transaction لضمان سلامة البيانات
        return DB::transaction(function () use ($finishedProductId, $productionOrderId, $quantity, $productionDate, $expiryType, $expiryValue) {
            
            $order = ProductionOrder::findOrFail($productionOrderId);

            $produced = FinishedProductBatch::where('production_order_id', $productionOrderId)
                ->sum('quantity');

            $remaining = $order->quantity - $produced;

            if ($remaining <= 0) {
                throw new InsufficientQuantityException("تم إنتاج كامل الكمية بالفعل");
            }

            if ($quantity > $remaining) {
                throw new InsufficientQuantityException("الكمية المتبقية فقط: $remaining");
            }

            // ✔ حساب تاريخ الصلاحية
            $productionDateCarbon = Carbon::parse($productionDate);

            $expiryDate = $expiryType === 'year'
                ? $productionDateCarbon->copy()->addYears($expiryValue)
                : $productionDateCarbon->copy()->addMonths($expiryValue);

            $batch = $this->dao->create([
                'finished_product_id' => $finishedProductId,
                'production_order_id' => $productionOrderId,
                'batch_number' => 'BATCH-' . now()->format('Ymd') . '-' . Str::random(4),
                'quantity' => $quantity,
                'remaining_quantity' => 0,
                'status' => 'created',
                'production_date' => $productionDate,
                'expiry_date' => $expiryDate->format('Y-m-d'),
            ]);

            // 🔥 البحث عن المرحلة النشطة حالياً (مرحلة "إرسال") وإنهائها تلقائياً
            $activeStage = ProductionStage::where('production_order_id', $productionOrderId)
                ->where('status', 'active')
                ->first();

            if ($activeStage) {
                $this->stageService->completeStage($activeStage->id);
            }

            return $batch;
        });
    }

// دالة إرسال الدفعة
public function sendBatch($batchId) {
    // نحدد الحالة فقط
    return $this->dao->updateStatus($batchId, 'sent');
}

// دالة استلام الدفعة
public function receiveBatch($batchId) {
    $batch = FinishedProductBatch::findOrFail($batchId);
    
    // عند الاستلام نقوم بتحديث الحالة وتفعيل الكمية للمخزون معاً
    return $this->dao->updateStatus($batchId, 'received', [
        'remaining_quantity' => $batch->quantity
    ]);
}

public function getTasksForReceive()
{
    return $this->dao->getTasksForReceive();
}



}
