<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProductionStageController;
use App\Http\Controllers\RawMaterialTaskController;
use App\Http\Controllers\FinishedProductBatchController;
use App\Http\Controllers\FinishedProductTaskController;
use App\Http\Controllers\FinishedProductWarehouseController;
use App\Http\Controllers\DistributionTaskController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\InvoiceController;

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class,'login']);
Route::post('/password/forgot', [AuthController::class,'sendResetPasswordOtp']);
Route::post('/password/reset', [AuthController::class,'resetPassword']);

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin|super_admin'])->group(function () {

    Route::post('/employee', [AdminController::class,'createEmployee']);
    Route::get('/employees', [AdminController::class,'employees']);
    Route::put('/employees/{id}', [AdminController::class,'updateEmployee']);
    Route::delete('/user/{id}', [AdminController::class,'deleteUser']);
    Route::patch('/user/{id}/toggle', [AdminController::class,'toggleVerify']);
});

/*
|--------------------------------------------------------------------------
| Super Admin
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| Raw Material Tasks
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/tasks/raw-materials/receive', [RawMaterialTaskController::class, 'createReceiveTask']);
    Route::post('/tasks/raw-materials/send', [RawMaterialTaskController::class, 'createSendTask']);

    Route::get('/tasks/raw-materials', [RawMaterialTaskController::class, 'listTasks']);
    Route::middleware(['role:admin|super_admin'])->get('/tasks/raw-materials/admin', [RawMaterialTaskController::class, 'adminListTasks']);

    Route::post('/tasks/raw-materials/{id}/submit-receive-input', [RawMaterialTaskController::class, 'submitReceiveInput']);
    Route::post('/tasks/raw-materials/{id}/confirm-receive', [RawMaterialTaskController::class, 'confirmReceive']);
    Route::post('/tasks/raw-materials/{id}/confirm-send', [RawMaterialTaskController::class, 'confirmSend']);

    Route::get('/inventory/summary', [RawMaterialTaskController::class, 'inventorySummary']);

    Route::post('/tasks/raw-materials/notes', [RawMaterialTaskController::class, 'addNote']);
});

/*
|--------------------------------------------------------------------------
| Production
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    Route::middleware('role:admin|super_admin')
        ->post('/production-orders', [ProductionOrderController::class, 'create']);

    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/production-orders', [ProductionOrderController::class, 'currentTasks']);

    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/start', [ProductionStageController::class, 'startOrder']);

    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/accept', [ProductionStageController::class,'acceptOrder']);

    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/reject', [ProductionStageController::class,'rejectOrder']);

    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/pause', [ProductionStageController::class, 'pauseOrder']);

    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/resume', [ProductionStageController::class, 'resumeOrder']);

    Route::middleware('role:production_employee')
        ->post('/production-stages/{stageId}/complete', [ProductionStageController::class, 'completeStage']);

    Route::middleware('role:production_employee')
        ->post('/finished-product-batches', [FinishedProductBatchController::class, 'create']);
});

/*
|--------------------------------------------------------------------------
| Finished Products Warehouse
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin|super_admin'])->group(function () {

    Route::post('/finished-product-tasks/send', [FinishedProductTaskController::class, 'createSendTask']);
});

Route::middleware(['auth:sanctum', 'role:product_storekeeper|admin|super_admin'])->group(function () {

    Route::post('/finished-product-tasks/{id}/confirm-send', [FinishedProductTaskController::class, 'confirmSend']);
});

/*
|--------------------------------------------------------------------------
| Distribution Tasks
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin|super_admin'])
    ->prefix('distribution-tasks')
    ->group(function () {

        Route::post('/', [DistributionTaskController::class, 'store']);
        Route::get('/', [DistributionTaskController::class, 'index']);
        Route::get('/drivers', [DistributionTaskController::class, 'drivers']);
        Route::get('/regions', [RegionController::class, 'index']);
        Route::get('/{id}', [DistributionTaskController::class, 'show']);
        Route::put('/{id}', [DistributionTaskController::class, 'update']);
        Route::get('/driver/{id}', [DistributionTaskController::class, 'driverTasks']);

        // ✅ تم التصحيح
        Route::get('/today', [DistributionTaskController::class, 'todayTasks']);
        Route::get('/driver/{driverId}/today', [DistributionTaskController::class, 'driverTodayTasks']);
    });

/*
|--------------------------------------------------------------------------
| Driver Actions
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:driver|admin|super_admin'])->group(function () {

    Route::get('/my-tasks/today', [DistributionTaskController::class, 'myTodayTask']);
    Route::post('/my-tasks/{id}/start', [DistributionTaskController::class, 'startTask']);
    Route::post('/my-tasks/{id}/complete', [DistributionTaskController::class, 'completeTask']);
    Route::get('/my-tasks/daily', [DistributionTaskController::class, 'myDailyTasks']);
});

/*
|--------------------------------------------------------------------------
| Sales Flow (Driver)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {

    Route::post('/scan-store', [StoreController::class, 'scanStore']);
    Route::post('/sales', [SaleController::class, 'createSale']);
    Route::post('/sales/{saleId}/items', [SaleController::class, 'addItems']);
    Route::post('/sales/{saleId}/confirm', [InvoiceController::class, 'confirmSale']);
});
