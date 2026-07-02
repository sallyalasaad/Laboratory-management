<?php



namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Auth;
class ForecastController extends Controller
{
   public function forecast(Request $request)
{
    $user = Auth::user();

    if (!$user || !in_array($user->role, ['admin', 'accountant'])) {
        return response()->json([
            'ok' => false,
            'message' => 'Unauthorized'
        ], 403);
    }

    $month = $request->input('month');

    $response = Http::get(
        'http://127.0.0.1:8001/forecast',
        [
            'target_month' => $month
        ]
    );

    return $response->json();
}}
