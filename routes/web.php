<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/debug-time', function () {
    return [
        'now' => now()->format('Y-m-d H:i:s'),
        'app_timezone' => config('app.timezone'),
        'php_timezone' => date_default_timezone_get(),
        'utc_now' => now()->utc()->format('Y-m-d H:i:s'),
    ];
});

Route::get('/', function () {
    return view('welcome');
});
