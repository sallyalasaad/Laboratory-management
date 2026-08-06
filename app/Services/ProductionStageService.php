<?php
namespace App\Services;

use App\DAO\ProductionStageDAO;
use App\DAO\ProductionOrderDAO;

class ProductionStageService
{
    protected $stageDao;
    protected $orderDao;

    public function __construct(ProductionStageDAO $stageDao, ProductionOrderDAO $orderDao)
    {
        $this->stageDao = $stageDao;
        $this->orderDao = $orderDao;
    }
    public function startOrder($orderId)
    {
        $order = $this->orderDao->findById($orderId);

        if (!$order) {
            return ['error' => true, 'message' => 'الطلب غير موجود', 'status_code' => 404];
        }

        if ($order->status === 'rejected') {
            return ['error' => true, 'message' => 'تم رفض الطلب ولا يمكن بدء الإنتاج', 'status_code' => 422];
        }

        // ✅ تحقق من أن الإنتاج استلم مواد
        $hasStock = \App\Models\RawMaterialTask::where('status', 'received_by_production')
            ->exists();

        if (!$hasStock) {
            return [
                'error' => true,
                'message' => 'لا يمكن بدء الإنتاج: لم يتم استلام مواد أولية من المستودع',
                'status_code' => 422
            ];
        }

        // بدء الإنتاج
        $order->status = 'in_progress';
        $order->save();

        $firstStage = $order->stages()->first();
        if ($firstStage) {
            $firstStage->status = 'active';
            $firstStage->save();
        }

        return [
            'error' => false,
            'message' => 'تم بدء الإنتاج',
            'order_status' => $order->status,
            'order_id' => $order->id
        ];
    }
    public function completeStage($stageId)
{
    $stage = $this->stageDao->findById($stageId);
    if(!$stage || $stage->status !== 'active'){
        return ['error' => true, 'message' => 'المرحلة غير نشطة أو غير موجودة'];
    }

    $this->stageDao->updateStatus($stage, 'done');

    $stages = $this->stageDao->findByOrderId($stage->production_order_id);
    foreach($stages as $s){
        if($s->status === 'pending'){
            $this->stageDao->updateStatus($s, 'active');
            return [
                'error' => false,
                'is_completed' => false,
                'next_stage' => $s
            ];
        }
    }

    // إذا لم توجد مرحلة معلقة، فهذه هي المرحلة الأخيرة -> إكمال الطلب
    $order = $this->orderDao->findById($stage->production_order_id);
    $this->orderDao->updateStatus($order, 'completed');

    return [
        'error' => false,
        'is_completed' => true,
        'message' => 'تم إنهاء جميع مراحل الإنتاج وأصبح الطلب مكتملًا',
        'order' => $order
    ];
}

    public function pauseOrder($orderId)
    {
        $order = $this->orderDao->findById($orderId);
        return $this->orderDao->updateStatus($order, 'paused');
    }

    public function resumeOrder($orderId)
    {
        $order = $this->orderDao->findById($orderId);
        return $this->orderDao->updateStatus($order, 'in_progress');
    }
    public function rejectOrder($orderId)
    {
        $order = $this->orderDao->findById($orderId);
        return $this->orderDao->updateStatus($order,'rejected');
    }
//استئناف مرحلة
    public function resumeStage($stageId)
    {
        $stage = $this->stageDao->findById($stageId);

        if (!$stage) {
            return ['error' => true, 'message' => 'Stage not found'];
        }

        if ($stage->status !== 'pending') {
            return ['error' => true, 'message' => 'Only paused stage can be resumed'];
        }

        // 1. رجّع المرحلة active
        $this->stageDao->updateStatus($stage, 'active');

        // 2. رجّع الطلب in_progress
        $order = $this->orderDao->findById($stage->production_order_id);
        $this->orderDao->updateStatus($order, 'in_progress');

        return [
            'error' => false,
            'message' => 'Stage resumed and order resumed',
            'stage' => $stage,
            'order' => $order
        ];
    }
//إيقاف مرحلة

    public function pauseStage($stageId)
    {
        $stage = $this->stageDao->findById($stageId);

        if (!$stage) {
            return ['error' => true, 'message' => 'Stage not found'];
        }

        if ($stage->status !== 'active') {
            return ['error' => true, 'message' => 'Only active stage can be paused'];
        }

        // 1. وقف المرحلة
        $this->stageDao->updateStatus($stage, 'pending');

        // 2. وقف الطلب المرتبط
        $order = $this->orderDao->findById($stage->production_order_id);
        $this->orderDao->updateStatus($order, 'paused');

        return [
            'error' => false,
            'message' => 'Stage paused and order paused',
            'stage' => $stage,
            'order' => $order
        ];
    }




}
