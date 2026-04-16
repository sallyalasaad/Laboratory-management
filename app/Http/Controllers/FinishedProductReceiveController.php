<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FinishedProductReceiveService;

class FinishedProductReceiveController extends Controller
{
    protected $service;

    public function __construct(FinishedProductReceiveService $service)
    {
        $this->service = $service;
    }

    public function confirmReceive($taskId)
    {
        try {
            $this->service->confirmReceive($taskId);

            return response()->json([
                'message' => 'Products received and moved to car stock successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
