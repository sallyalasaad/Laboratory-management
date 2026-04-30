<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProductionStageService;
use App\Services\ProductionOrderService;

class ProductionStageController extends Controller
{
    protected $stageService;
    protected $orderService;

    public function __construct(
        ProductionStageService $stageService,
        ProductionOrderService $orderService
    ) {
        $this->stageService = $stageService;
        $this->orderService = $orderService;
    }

    /*
    =========================
    قبول الطلب
    =========================
    */
    public function acceptOrder($orderId)
    {
        $order = $this->orderService->updateStatus($orderId, 'accepted');

        return response()->json([
            'message' => 'تم قبول طلب الإنتاج',
            'order' => $order
        ]);
    }

    /*
    =========================
    رفض الطلب
    =========================
    */
    public function rejectOrder($orderId)
    {
        $order = $this->orderService->updateStatus($orderId, 'rejected');

        return response()->json([
            'message' => 'تم رفض طلب الإنتاج',
            'order' => $order
        ]);
    }

    /*
    =========================
    تأكيد استلام المواد
    =========================
    */
        public function confirmMaterials($orderId)
    {
        $task = \App\Models\RawMaterialTask::where('route', 'send_to_production')
            ->where('status', 'completed')
            ->whereJsonContains('details->production_order_id', $orderId)
            ->first();

        if (!$task) {
            return response()->json([
                'message' => 'لم يتم إرسال المواد لهذا الطلب بعد'
            ], 422);
        }

        $order = $this->orderService->updateStatus($orderId, 'materials_received');

        return response()->json([
            'message' => 'تم تأكيد استلام المواد',
            'order' => $order
        ]);
    }
    /*
    =========================
    بدء الإنتاج
    =========================
    */public function startOrder($orderId)
{
    $result = $this->stageService->startOrder($orderId);

    if ($result['error'] ?? false) {
        return response()->json($result, $result['status_code'] ?? 422);
    }

    return response()->json($result, 200);
}

    /*
    =========================
    إنهاء مرحلة
    =========================
    */
    public function completeStage($stageId)
    {
        $stage = $this->stageService->completeStage($stageId);

        return response()->json([
            'message' => 'تم إنهاء المرحلة',
            'next_stage' => $stage
        ]);
    }

    /*
    =========================
    إيقاف الطلب
    =========================
    */
    public function pauseOrder($orderId)
    {
        $order = $this->stageService->pauseOrder($orderId);

        return response()->json([
            'message' => 'تم إيقاف الطلب',
            'order' => $order
        ]);
    }

    /*
    =========================
    استئناف الطلب
    =========================
    */
    public function resumeOrder($orderId)
    {
        $order = $this->stageService->resumeOrder($orderId);

        return response()->json([
            'message' => 'تم استئناف الطلب',
            'order' => $order
        ]);
    }

    public function pauseStage($stageId)
    {
        return response()->json(
            $this->stageService->pauseStage($stageId)
        );
    }

    public function resumeStage($stageId)
    {
        return response()->json(
            $this->stageService->resumeStage($stageId)
        );
    }














}
