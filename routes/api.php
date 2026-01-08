<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [AuthController::class, 'sendResetPasswordOtp']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function() {
    Route::post('/employee', [AdminController::class,'createEmployee']);
    Route::get('/employees', [AdminController::class,'employees']);
    Route::delete('/user/{id}', [AdminController::class,'deleteUser']);
    Route::put('/employees/{id}', [AdminController::class, 'updateEmployee']);
});

Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function() {
    Route::post('/create-admin', function(Request $request){
        return \App\Models\User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
            'role' => 'admin',
            'is_verified' => true
        ]);
    });

});


Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {

    // تفعيل / تعطيل مستخدم
    Route::patch('/user/{id}/toggle', [AdminController::class, 'toggleVerify']);

});
