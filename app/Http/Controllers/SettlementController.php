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
    public function finalize($driverId)
    {
        $result = $this->service->finalizeAndSync($driverId);

        if (!$result['success']) {
            return response()->json(['ok' => false, 'message' => $result['message']], 400);
        }

        return response()->json([
            'ok' => true,
            'message' => $result['message'],
            'data' => [
                'total_received' => $result['total_cash_to_receive']
            ]
        ]);
    }
    // App\Http\Controllers\SettlementController.php

public function index()
{
    $settlements = $this->service->getAllSettlements();
    return response()->json([
        'ok' => true,
        'data' => $settlements
    ]);
}
}