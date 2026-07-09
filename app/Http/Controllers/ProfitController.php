<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProfitCalculationService;

class ProfitController extends Controller
{
    protected $profitService;

    public function __construct(ProfitCalculationService $profitService)
    {
        $this->profitService = $profitService;
    }

    public function getReport(Request $request)
{
    $request->validate([
        'cheese_cost' => 'required|numeric',
        'labneh_cost' => 'required|numeric',
        'month'       => 'nullable|integer|between:1,12', // أضفنا الشهر للتحقق
    ]);

    // نمرر القيم الثلاث التي تتوقعها الـ Service
    $report = $this->profitService->getMonthlyPerformance(
        $request->input('month', date('m')), // الشهر الحالي افتراضياً
        $request->cheese_cost, 
        $request->labneh_cost
    );

    return response()->json($report);
}
}