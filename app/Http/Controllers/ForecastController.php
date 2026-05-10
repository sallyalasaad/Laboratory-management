<?php



namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;

class ForecastController extends Controller
{
public function forecast(Request $request)
{
    $month = $request->input('month');

    $response = Http::get(
        'http://127.0.0.1:8001/forecast',
        [
            'target_month' => $month
        ]
    );

    return $response->json();
}
}
