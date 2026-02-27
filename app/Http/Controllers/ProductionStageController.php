<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProductionStageService;

class ProductionStageController extends Controller
{
    protected $service;

    public function __construct(ProductionStageService $service)
    {
        $this->service = $service;
    }

    public function startOrder($orderId)
    {
        $order = $this->service->startOrder($orderId);
        if(!$order) return response()->json(['message'=>'Order not found'],404);

        return response()->json(['message'=>'Order started','order'=>$order]);
    }

    public function completeStage($stageId)
    {
        $nextStage = $this->service->completeStage($stageId);
        return response()->json([
            'message'=>'Stage completed',
            'next_stage'=>$nextStage
        ]);
    }

    public function pauseOrder($orderId)
    {
        $order = $this->service->pauseOrder($orderId);
        return response()->json(['message'=>'Order paused','order'=>$order]);
    }

    public function resumeOrder($orderId)
    {
        $order = $this->service->resumeOrder($orderId);
        return response()->json(['message'=>'Order resumed','order'=>$order]);
    }
}
