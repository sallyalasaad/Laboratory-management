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
        if(!$order) return null;

        $order = $this->orderDao->updateStatus($order, 'in_progress');

        $activeStage = $this->stageDao->getActiveStage($orderId);
        if(!$activeStage){
            $stages = $this->stageDao->findByOrderId($orderId);
            if($stages->count() > 0){
                $this->stageDao->updateStatus($stages->first(), 'active');
            }
        }

        return $order;
    }

    public function completeStage($stageId)
    {
        $stage = $this->stageDao->findById($stageId);
        if(!$stage || $stage->status !== 'active'){
            return null;
        }

        $this->stageDao->updateStatus($stage, 'done');

        $stages = $this->stageDao->findByOrderId($stage->production_order_id);
        foreach($stages as $s){
            if($s->status === 'pending'){
                $this->stageDao->updateStatus($s, 'active');
                return $s;
            }
        }

        $order = $this->orderDao->findById($stage->production_order_id);
        $this->orderDao->updateStatus($order, 'completed');

        return null;
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
}
