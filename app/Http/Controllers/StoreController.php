<?php

namespace App\Http\Controllers;

use App\Models\DistributionTask;
use App\Models\Sale;
use App\Models\Store;
use App\Services\VisitService;
use Illuminate\Http\Request;

class StoreController extends Controller
{public function scanStore(Request $request, \App\Services\StoreService $service)
{
    $request->validate([
        'barcode' => 'required'
    ]);

    $result = $service->scanStore(auth()->user(), $request->barcode);

    if (!$result['ok']) {
        return response()->json([
            'message' => $result['message']
        ], $result['code']);
    }

    return response()->json($result['data']);
}
}
