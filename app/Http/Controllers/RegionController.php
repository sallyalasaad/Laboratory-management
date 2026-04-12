<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{

    /* عرض المناطق*/public function index()
{
    $region = Region::withCount('stores')->get();

    return response()->json([
        'message' => 'Regions fetched successfully',
        'data' => $region
    ]);
}

}
