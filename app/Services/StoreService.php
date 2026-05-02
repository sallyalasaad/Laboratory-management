<?php

namespace App\Services;

use App\DAO\StoreDAO;
class StoreService
{
    private $dao;
    private $taskGuard;
    private $visitService;

    public function __construct(
        StoreDAO $dao,
        TaskTimeGuard $taskGuard,
        VisitService $visitService
    ) {
        $this->dao = $dao;
        $this->taskGuard = $taskGuard;
        $this->visitService = $visitService;
    }

    public function scanStore($user, $barcode)
    {
        $store = $this->dao->findStoreByBarcode($barcode);

        if (!$store) {
            return ['ok' => false, 'code' => 404, 'message' => 'Store not found'];
        }

        $task = $this->dao->getActiveTask($user->id);

        if (!$task) {
            return ['ok' => false, 'code' => 404, 'message' => 'No active task'];
        }

        $this->taskGuard->check($task);

        $storePivot = $this->dao->getTaskStore($task, $store->id);

        if (!$storePivot) {
            return ['ok' => false, 'code' => 400, 'message' => 'Store not in your task'];
        }

        if ($store->type === 'retail') {
            $this->visitService->markVisited($task, $store->id);
        }

        $sale = $this->dao->createOrGetSale(
            $user->id,
            $task->id,
            $store->id
        );

        return [
            'ok' => true,
            'data' => [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'type' => $store->type,
                    'barcode' => $store->barcode,
                    'lat' => $store->lat,
                    'lng' => $store->lng,
                ],
                'task' => [
                    'id' => $task->id,
                    'date' => $task->date,
                    'region' => $task->region->name,
                    'status' => $task->status,
                ],
                'invoice' => [
                    'id' => $sale->id,
                    'status' => $sale->status,
                    'date' => $sale->date,
                    'total_amount' => $sale->total_amount,
                    'items_count' => $sale->items()->count(),
                ],
                'rules' => [
                    'must_sell' => $store->type === 'wholesale',
                    'allow_add_items' => true,
                    'require_scan' => true
                ]
            ]
        ];
    }
}
