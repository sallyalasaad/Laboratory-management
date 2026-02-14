<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;

// تسجيل الدخول
Route::post('/login', [AuthController::class,'login']);
Route::post('/password/forgot', [AuthController::class,'sendResetPasswordOtp']);
Route::post('/password/reset', [AuthController::class,'resetPassword']);


// Admin + Super Admin
Route::middleware(['auth:sanctum', 'role:admin|super_admin'])->group(function () {

    Route::post('/employee', [AdminController::class,'createEmployee']);
    Route::get('/employees', [AdminController::class,'employees']);
    Route::put('/employees/{id}', [AdminController::class,'updateEmployee']);
    Route::delete('/user/{id}', [AdminController::class,'deleteUser']);
    Route::patch('/user/{id}/toggle', [AdminController::class,'toggleVerify']);

});


// فقط Super Admin
Route::middleware(['auth:sanctum', 'role:super_admin'])
    ->post('/create-admin', function (\Illuminate\Http\Request $request) {

        $request->validate([
            'name'     => 'required|string',
            'email'    => 'required|email|unique:users',
            'phone'    => 'required|unique:users',
            'password' => 'required|min:6'
        ]);

        $user = \App\Models\User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'password'    => bcrypt($request->password),
            'is_verified' => true
        ]);

        $user->assignRole('admin');

        return response()->json([
            'message' => 'تم إنشاء مدير بنجاح',
            'data'    => $user
        ]);
    });


// Raw material tasks: create by admin, handled by warehouse user
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/tasks/raw-materials/receive', [\App\Http\Controllers\RawMaterialTaskController::class, 'createReceiveTask']);
    Route::post('/tasks/raw-materials/send', [\App\Http\Controllers\RawMaterialTaskController::class, 'createSendTask']);

    // warehouse endpoints
    Route::get('/tasks/raw-materials', [\App\Http\Controllers\RawMaterialTaskController::class, 'listTasks']);
    Route::post('/tasks/raw-materials/{id}/confirm-receive', [\App\Http\Controllers\RawMaterialTaskController::class, 'confirmReceive']);
    Route::post('/tasks/raw-materials/{id}/confirm-send', [\App\Http\Controllers\RawMaterialTaskController::class, 'confirmSend']);

    // inventory summary
    Route::get('/inventory/summary', [\App\Http\Controllers\RawMaterialTaskController::class, 'inventorySummary']);
});
