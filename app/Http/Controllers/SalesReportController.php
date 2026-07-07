<?php

namespace App\Http\Controllers;

use App\Models\SaleItem;
use Illuminate\Http\Request;
use App\Services\SalesReportService;

class SalesReportController extends Controller
{
    protected $service;

    public function __construct(SalesReportService $service)
    {
        $this->service = $service;
    }

    // ✅ المبيعات اليومية
    public function daily(Request $request)
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id',
            'date' => 'required|date'
        ]);

        $data = $this->service->dailySales(
            $request->driver_id,
            $request->date
        );

        return response()->json([
            'driver_id' => $request->driver_id,
            'date' => $request->date,
            'data' => $data
        ]);
    }



    // ✅ المبيعات الشهرية
    public function monthly(Request $request)
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'nullable|integer'
        ]);

        $data = $this->service->monthlySales(
            $request->driver_id,
            $request->month,
            $request->year ?? date('Y')
        );

        return response()->json([
            'driver_id' => $request->driver_id,
            'month' => $request->month,
            'year' => $request->year ?? date('Y'),
            'data' => $data
        ]);
    }

// داخل App\Http\Controllers\SalesReportController.php

public function allDriversMonthly(Request $request)
{
    $request->validate([
        'month' => 'required|integer|min:1|max:12',
        'year' => 'nullable|integer'
    ]);

    $data = $this->service->allDriversMonthlySales(
        $request->month,
        $request->year ?? date('Y')
    );

    return response()->json([
        'month' => $request->month,
        'year' => $request->year ?? date('Y'),
        'data' => $data
    ]);
}




}
