<?php

namespace App\DAO;
use Illuminate\Support\Facades\DB;
class SettlementDAO{
public function getDriverSettlementData($driverId)
{
    // هنا قمنا بتغيير driver_id إلى user_id ليطابق تعريف الموديل
    $task = \App\Models\DistributionTask::where('user_id', $driverId)
        ->where('status', 'in_progress')
        ->first();

    $visitedStoresCount = 0;
    $totalTargetStores = 0;
    $totalCash = 0;

    if ($task) {
        // حساب عدد المحلات المزارة
        $visitedStoresCount = $task->stores()->wherePivot('visited', true)->count();
        // حساب إجمالي المحلات المطلوبة في هذه المهمة
        $totalTargetStores = $task->stores()->count();
        
        // حساب إجمالي مبالغ المبيعات المؤكدة لهذه المهمة
        $totalCash = \App\Models\Sale::where('distribution_task_id', $task->id)
            ->where('status', 'confirmed')
            ->sum('total_amount');
    }

    return [
        'visited_stores' => $visitedStoresCount,
        'total_stores' => $totalTargetStores,
        'total_cash' => (float) $totalCash
    ];
}
}
