<?php

namespace App\DAO;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\DistributionTask;


class SettlementDAO{
public function getDriverSettlementData($driverId)
{
    // جلب آخر مهمة للسائق بدلاً من التقيد بـ in_progress
    $task = \App\Models\DistributionTask::where('user_id', $driverId)
        ->latest()
        ->first();

    $visitedStoresCount = 0;
    $totalTargetStores = 0;

    if ($task) {
        $visitedStoresCount = $task->stores()->wherePivot('visited', true)->count();
        $totalTargetStores = $task->stores()->count();
    }

    // هنا نستخدم الدالة الجديدة التي أضفتِها لجلب الكاش (المبيعات المؤكدة وغير المصفاة)
    $totalCash = $this->getUnsettledSalesByDriver($driverId);

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
    return \App\Models\User::whereHas('distributionTasks')
        ->get()
        ->map(function ($user) {
            $latestTask = \App\Models\DistributionTask::where('user_id', $user->id)->latest()->first();
            
            return [
                'driver_name' => $user->name,
                'points' => $latestTask ? ($latestTask->stores()->wherePivot('visited', true)->count() . ' / ' . $latestTask->stores()->count()) : '0 / 0',
                'total_cash' => (float) $this->getUnsettledSalesByDriver($user->id),
                'task_id' => $latestTask ? $latestTask->id : null,
                'driver_id' => $user->id
            ];
        });
}

public function getUnsettledSalesByDriver($driverId)
    {
        return \App\Models\Sale::whereHas('distributionTask', function($query) use ($driverId) {
            $query->where('user_id', $driverId);
        })
        ->where('status', 'confirmed')
        ->sum('total_amount');
    }

}
