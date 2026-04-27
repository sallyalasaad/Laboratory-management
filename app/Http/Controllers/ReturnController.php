<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    protected $service;

    public function __construct(ReturnService $service)
    {
        $this->service = $service;
    }

    /**
     * 🔥 زر واحد فقط: رجّع كل شيء
     */
    public function autoReturn(Request $request)
    {
        $userId = auth()->id();

        $result = $this->service->autoReturn($userId);

        if (!($result['success'] ?? true)) {
            return response()->json($result, 400);
        }

        return response()->json([
            'message' => $result['message']
        ]);
    }

    public function myCarStock(Request $request)
    {
        return response()->json(
            $this->service->getCarStock(auth()->id())
        );
    }



    // 🔹 عرض مخزون سائق
    public function driverStock($driverId)
    {
        $driver = User::find($driverId);

        if (!$driver || !$driver->hasRole('driver')) {
            return response()->json([
                'message' => 'Invalid driver'
            ], 422);
        }

        $stock = $this->service->getDriverStock($driverId);

        return response()->json([
            'driver_id' => $driverId,
            'stock' => $stock
        ]);
    }
    public function driverStockReport($driverId)
    {
        $driver = User::find($driverId);

        if (!$driver || !$driver->hasRole('driver')) {
            return response()->json([
                'message' => 'Invalid driver'
            ], 422);
        }

        $data = $this->service->getDriverReport($driverId);

        // ✅ totals الجديدة (مع فصل المبيعات)
        $totalReceived = collect($data)->sum('received_quantity');
        $totalSold = collect($data)->sum('sold_quantity');
        $totalRemaining = collect($data)->sum('remaining_in_car');
        $totalReturned  = collect($data)->sum('returned_to_warehouse');

        return response()->json([
            'driver_id' => $driverId,
            'report' => $data,

            // 📊 totals بعد التعديل
            'totals' => [
                'received_quantity' => (float) $totalReceived,
                'sold_quantity' => (float) $totalSold,
                'remaining_in_car' => (float) $totalRemaining,
                'returned_to_warehouse' => (float) $totalReturned,
            ]
        ]);
    }



}
