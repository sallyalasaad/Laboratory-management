<?php

namespace App\Services;

use App\DAO\SettlementDAO; // تأكد من استيراد الـ DAO الخاص بالتصفية

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
}