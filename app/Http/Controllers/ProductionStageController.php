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
        $order = $this->orderService->updateStatus($orderId, 'materials_received');

        return response()->json([
            'message' => 'تم تأكيد استلام المواد الأولية',
            'order' => $order
        ]);
    }

    /*
    =========================
    بدء الإنتاج
    =========================
    */
    public function startOrder($orderId)
    {
        $order = $this->stageService->startOrder($orderId);

        return response()->json([
            'message' => 'تم بدء الإنتاج',
            'order' => $order
        ]);
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
}
