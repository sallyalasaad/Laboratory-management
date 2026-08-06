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
    }public function finalizeAndSync($driverId)
    {
        return DB::transaction(function () use ($driverId) {
            // 1. بدلاً من البحث عن مهمة نشطة، سنبحث عن جميع المبيعات المؤكدة للسائق
            // سنفترض أننا نريد تصفية كل المبيعات التي لم تأخذ حالة 'settled' بعد
            $totalCollected = \App\Models\Sale::whereHas('distributionTask', function($query) use ($driverId) {
                $query->where('user_id', $driverId);
            })
            ->where('status', 'confirmed')
            // يمكنك إضافة شرط إضافي هنا: ->where('is_settled', false) 
            ->sum('total_amount');

            if ($totalCollected <= 0) {
                return ['success' => false, 'message' => 'لا توجد مبالغ مالية جديدة لتصفيتها'];
            }

            // 2. هنا نقوم بعملية التصفية (تحديث حالة المبيعات لتصبح مصفاة)
            \App\Models\Sale::whereHas('distributionTask', function($query) use ($driverId) {
                $query->where('user_id', $driverId);
            })
            ->where('status', 'confirmed')
            ->update(['status' => 'settled']); // نغير الحالة لـ settled حتى لا تُحسب مرة أخرى

            return [
                'success' => true,
                'message' => 'تم إنهاء التصفية بنجاح وتسليم المبلغ',
                'total_cash_to_receive' => (float) $totalCollected
            ];
        });
    }
public function getAllSettlements()
{
    return $this->dao->getAllDriversSettlementData();
}
}