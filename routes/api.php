<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RegionController;

use App\Http\Controllers\RawMaterialTaskController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProductionStageController;

use App\Http\Controllers\FinishedProductBatchController;
use App\Http\Controllers\FinishedProductTaskController;

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StoreController;

use App\Http\Controllers\DistributionTaskController;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

// تسجيل الدخول
Route::post('/login', [AuthController::class,'login']);
Route::post('/password/forgot', [AuthController::class,'sendResetPasswordOtp']);
Route::post('/password/reset', [AuthController::class,'resetPassword']);

/*
|--------------------------------------------------------------------------
| Admin / Super Admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:admin|super_admin'])->group(function () {

    Route::post('/employee', [AdminController::class,'createEmployee']);
    Route::get('/employees', [AdminController::class,'employees']);
    Route::put('/employees/{id}', [AdminController::class,'updateEmployee']);
    Route::delete('/user/{id}', [AdminController::class,'deleteUser']);
    Route::patch('/user/{id}/toggle', [AdminController::class,'toggleVerify']);

    // إنشاء مدير (super admin only endpoint inline handled)
    Route::post('/create-admin', function (Request $request) {

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
            'message' => 'Admin created successfully',
            'data' => $user
        ]);
    })->middleware('role:super_admin');
});

/*
|--------------------------------------------------------------------------
| Raw Material Tasks
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/tasks/raw-materials/receive', [RawMaterialTaskController::class,'createReceiveTask']);
    Route::post('/tasks/raw-materials/send', [RawMaterialTaskController::class,'createSendTask']);

    Route::get('/tasks/raw-materials', [RawMaterialTaskController::class,'listTasks']);
    Route::get('/tasks/raw-materials/admin', [RawMaterialTaskController::class,'adminListTasks'])
        ->middleware('role:admin|super_admin');

    Route::post('/tasks/raw-materials/{id}/submit-receive-input', [RawMaterialTaskController::class,'submitReceiveInput']);
    Route::post('/tasks/raw-materials/{id}/confirm-receive', [RawMaterialTaskController::class,'confirmReceive']);
    Route::post('/tasks/raw-materials/{id}/confirm-send', [RawMaterialTaskController::class,'confirmSend']);

    Route::get('/inventory/summary', [RawMaterialTaskController::class,'inventorySummary']);

    Route::post('/tasks/raw-materials/notes', [RawMaterialTaskController::class,'addNote']);

    Route::get('/task/raw-materials/notes', [RawMaterialTaskController::class,'adminListNotes'])
        ->middleware('role:admin|super_admin');

    Route::patch('/tasks/raw-materials/notes/{noteId}/mark-read', [RawMaterialTaskController::class,'markNoteRead'])
        ->middleware('role:admin|super_admin');

    Route::delete('/tasks/raw-materials/{id}/notes/delete-read', [RawMaterialTaskController::class,'deleteReadNotes'])
        ->middleware('role:admin|super_admin');
});

/*
|--------------------------------------------------------------------------
| Production Orders
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/notes/send', [ProductionOrderController::class,'sendNote']);
    Route::get('/notes', [ProductionOrderController::class,'notes']);
    Route::get('/notes/unread', [ProductionOrderController::class,'unreadNotes']);
    Route::patch('/notes/{id}/read', [ProductionOrderController::class,'markAsRead']);
    Route::delete('/notes/{id}', [ProductionOrderController::class,'deleteNote']);

    Route::post('/production-orders', [ProductionOrderController::class,'create'])
        ->middleware('role:admin|super_admin');

    Route::get('/production-orders', [ProductionOrderController::class,'currentTasks'])
        ->middleware('role:production_employee|admin|super_admin');

    Route::get('/ordersHistory', [ProductionOrderController::class,'ordersHistory'])
        ->middleware('role:production_employee|admin|super_admin');

    Route::get('/allorders', [ProductionOrderController::class,'allorders'])
        ->middleware('role:production_employee|admin|super_admin');

    Route::get('/order/{id}', [ProductionOrderController::class,'show'])
        ->middleware('role:production_employee|admin|super_admin');

    Route::post('/production-orders/{orderId}/start', [ProductionStageController::class,'startOrder'])
        ->middleware('role:production_employee');

    Route::post('/production-orders/{orderId}/accept', [ProductionStageController::class,'acceptOrder'])
        ->middleware('role:production_employee');

    Route::post('/production-orders/{orderId}/reject', [ProductionStageController::class,'rejectOrder'])
        ->middleware('role:production_employee');

    Route::post('/production-orders/{orderId}/pause', [ProductionStageController::class,'pauseOrder'])
        ->middleware('role:production_employee');

    Route::post('/production-orders/{orderId}/resume', [ProductionStageController::class,'resumeOrder'])
        ->middleware('role:production_employee');

    Route::post('/production-stages/{stageId}/complete', [ProductionStageController::class,'completeStage'])
        ->middleware('role:production_employee');

    Route::post('/finished-product-batches', [FinishedProductBatchController::class,'create'])
        ->middleware('role:production_employee');

    Route::get('/production-orders/{orderId}/batches', [FinishedProductBatchController::class,'list1'])
        ->middleware('role:production_employee|admin|super_admin');

    Route::get('/production/incoming', [ProductionOrderController::class,'incomingTasks'])
        ->middleware('role:production_employee');

    Route::get('/production-orders/{orderId}/stages', [ProductionOrderController::class,'listOrders']);
});

/*
|--------------------------------------------------------------------------
| Finished Product Tasks
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/finished-product-tasks/send', [FinishedProductTaskController::class,'createSendTask'])
        ->middleware('role:admin|super_admin');

    Route::post('/finished-product-tasks/{id}/confirm-send', [FinishedProductTaskController::class,'confirmSend'])
        ->middleware('role:product_storekeeper|admin|super_admin');
});

/*
|--------------------------------------------------------------------------
| Distribution System (Driver Delivery)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:admin|super_admin'])
    ->prefix('distribution-tasks')
    ->group(function () {

        Route::post('/', [DistributionTaskController::class,'store']);
        Route::get('/', [DistributionTaskController::class,'index']);
        Route::get('/{id}', [DistributionTaskController::class,'show']);
        Route::put('/{id}', [DistributionTaskController::class,'update']);

        Route::get('/drivers', [DistributionTaskController::class,'drivers']);
        Route::get('/regions', [RegionController::class,'index']);

        Route::get('/driver/{id}', [DistributionTaskController::class,'driverTasks']);

        Route::get('/today', [DistributionTaskController::class,'todayTasks']);
        Route::get('/driver/{driverId}/today', [DistributionTaskController::class,'driverTodayTasks']);
    });

Route::middleware(['auth:sanctum', 'role:driver|admin|super_admin'])->group(function () {

    Route::get('/my-tasks/today', [DistributionTaskController::class,'myTodayTask']);
    Route::post('/my-tasks/{id}/start', [DistributionTaskController::class,'startTask']);
    Route::post('/my-tasks/{taskId}/store/{storeId}/visit', [DistributionTaskController::class,'visitStore']);
    Route::post('/my-tasks/{id}/complete', [DistributionTaskController::class,'completeTask']);
    Route::get('/my-tasks/daily', [DistributionTaskController::class,'myDailyTasks']);

    Route::post('/scan-store', [StoreController::class,'scanStore']);
});

/*
|--------------------------------------------------------------------------
| Sales Flow (Driver POS)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {

    Route::post('/sales', [SaleController::class,'createSale']);
    Route::post('/sales/{saleId}/items', [SaleController::class,'addItems']);
    Route::post('/sales/{saleId}/confirm', [InvoiceController::class,'confirmSale']);
});
