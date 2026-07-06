<?php

namespace App\Http\Controllers;

use App\Services\SettlementService; // تأكد من استيراد الـ Service الصحيح
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    protected $service;

    // يجب إضافة الـ Constructor هنا ليتم التعرف على $service
    public function __construct(SettlementService $service)
    {
        $this->service = $service;
    }

    public function getSummary($driverId)
    {
        // الآن سيعمل هذا السطر لأن $this->service أصبح معروفاً
        $summary = $this->service->getSettlementSummary($driverId);
        return response()->json($summary);
    }
}