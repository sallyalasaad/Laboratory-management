<?php

namespace App\DAO;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\DistributionTask;


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
public function getActiveTask($driverId)
    {
        return DistributionTask::where('user_id', $driverId)
            ->where('status', 'in_progress')
            ->first();
    }

    public function getConfirmedSalesAmount($taskId)
    {
        return Sale::where('distribution_task_id', $taskId)
            ->where('status', 'confirmed')
            ->sum('total_amount');
    }

    public function updateTaskStatus($taskId, array $data)
    {
        return DistributionTask::where('id', $taskId)->update($data);
    }
// App\DAO\SettlementDAO.php

public function getAllDriversSettlementData()
{
    return \App\Models\DistributionTask::where('status', 'in_progress')
        ->with('user') // جلب بيانات السائق
        ->get()
        ->map(function ($task) {
            return [
                'driver_name' => $task->user->name ?? 'Unknown',
                'points' => $task->stores()->wherePivot('visited', true)->count() . ' / ' . $task->stores()->count(),
                'total_cash' => (float) \App\Models\Sale::where('distribution_task_id', $task->id)
                                    ->where('status', 'confirmed')
                                    ->sum('total_amount'),
                'task_id' => $task->id,
                'driver_id' => $task->user_id
            ];
        });
}




}
