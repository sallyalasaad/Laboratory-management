<?php

namespace App\Services;

use App\DAO\SettlementDAO; // تأكد من استيراد الـ DAO الخاص بالتصفية
use Illuminate\Support\Facades\DB;

class SettlementService
{
    protected $dao;

    // يجب إضافة الـ Constructor هنا ليتم ربط الـ DAO
    public function __construct(SettlementDAO $dao)
    {
        $this->dao = $dao;
    }

    public function getSettlementSummary($driverId)
    {
        $data = $this->dao->getDriverSettlementData($driverId);
        
        return [
            'points' => "{$data['visited_stores']} / {$data['total_stores']}",
            'total_cash' => $data['total_cash'],
            'message' => 'المرتجعات تذهب للمستودع مباشرة'
        ];
    }
    public function finalizeAndSync($driverId)
    {
        return DB::transaction(function () use ($driverId) {
            $task = $this->dao->getActiveTask($driverId);

            if (!$task) {
                return ['success' => false, 'message' => 'لا توجد مهمة نشطة للسائق'];
            }

            // حساب المبيعات
            $totalCollected = $this->dao->getConfirmedSalesAmount($task->id);

            // إنهاء المهمة
            $this->dao->updateTaskStatus($task->id, [
                'status' => 'completed',
                'end_time' => now()
            ]);

            return [
                'success' => true,
                'message' => 'تم إنهاء التصفية بنجاح',
                'total_cash_to_receive' => (float) $totalCollected
            ];
        });
    }
    // App\Services\SettlementService.php

public function getAllSettlements()
{
    return $this->dao->getAllDriversSettlementData();
}
}