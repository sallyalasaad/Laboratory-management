<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AdminController,
    AuthController,
    ProductionOrderController,
    ProductionStageController,
    FinishedProductBatchController,
    FinishedProductTaskController,
    FinishedProductWarehouseController,
    RawMaterialTaskController,
    DistributionTaskController,
    RegionController,
    StoreController,
    SaleController,
    InvoiceController
};

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

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

        // Distribution Tasks (Admin)
        Route::prefix('distribution-tasks')->group(function () {

            Route::post('/', [DistributionTaskController::class, 'store']);
            Route::get('/', [DistributionTaskController::class, 'index']);
            Route::get('/{id}', [DistributionTaskController::class, 'show']);
            Route::put('/{id}', [DistributionTaskController::class, 'update']);

            Route::get('/drivers', [DistributionTaskController::class, 'drivers']);
            Route::get('/regions', [RegionController::class, 'index']);
            Route::get('/driver/{id}', [DistributionTaskController::class, 'driverTasks']);

            // ✅ FIXED (بدون تكرار)
            Route::get('/today', [DistributionTaskController::class, 'todayTasks']);
            Route::get('/driver/{driverId}/today', [DistributionTaskController::class, 'driverTodayTasks']);
        });

        // Finished Product Tasks
        Route::post('/finished-product-tasks/send', [FinishedProductTaskController::class, 'createSendTask']);
    });

    /*
    |--------------------------------------------------------------------------
    | Super Admin Only
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'role:super_admin'])->post('/create-admin', function (\Illuminate\Http\Request $request) {

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
    Route::middleware(['auth:sanctum'])->group(function () {

        Route::post('/tasks/raw-materials/receive', [RawMaterialTaskController::class, 'createReceiveTask']);
        Route::post('/tasks/raw-materials/send', [RawMaterialTaskController::class, 'createSendTask']);

        Route::get('/tasks/raw-materials', [RawMaterialTaskController::class, 'listTasks']);
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

        Route::middleware('role:production_employee|admin|super_admin')->group(function () {

            Route::get('/production-orders', [ProductionOrderController::class, 'currentTasks']);
            Route::get('/ordersHistory', [ProductionOrderController::class, 'ordersHistory']);
            Route::get('/allorders', [ProductionOrderController::class, 'allorders']);
            Route::get('/order/{id}', [ProductionOrderController::class, 'show']);

            Route::get('/production-orders/{orderId}/batches', [FinishedProductBatchController::class, 'list1']);
        });

        Route::middleware('role:production_employee')->group(function () {

            Route::post('/production-orders/{orderId}/start', [ProductionStageController::class, 'startOrder']);
            Route::post('/production-orders/{orderId}/accept', [ProductionStageController::class,'acceptOrder']);
            Route::post('/production-orders/{orderId}/reject', [ProductionStageController::class,'rejectOrder']);
            Route::post('/production-orders/{orderId}/pause', [ProductionStageController::class, 'pauseOrder']);
            Route::post('/production-orders/{orderId}/resume', [ProductionStageController::class, 'resumeOrder']);
            Route::post('/production-stages/{stageId}/complete', [ProductionStageController::class, 'completeStage']);

            Route::post('/finished-product-batches', [FinishedProductBatchController::class, 'create']);
        });

        Route::get('/production-orders/{orderId}/stages', [ProductionOrderController::class, 'listOrders']);
    });

    /*
    |--------------------------------------------------------------------------
    | Warehouse (Finished Products)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum','role:product_storekeeper|admin|super_admin'])->group(function () {

        Route::post('/finished-product-tasks/{id}/confirm-send', [FinishedProductTaskController::class, 'confirmSend']);
    });

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum','role:driver'])->group(function () {

        // Tasks
        Route::get('/my-tasks/today', [DistributionTaskController::class, 'myTodayTask']);
        Route::post('/my-tasks/{id}/start', [DistributionTaskController::class, 'startTask']);
        Route::post('/my-tasks/{taskId}/store/{storeId}/visit', [DistributionTaskController::class, 'visitStore']);
        Route::post('/my-tasks/{id}/complete', [DistributionTaskController::class, 'completeTask']);
        Route::get('/my-tasks/daily', [DistributionTaskController::class, 'myDailyTasks']);

        // Sales Flow
        Route::post('/scan-store', [StoreController::class, 'scanStore']);
        Route::post('/sales', [SaleController::class, 'createSale']);
        Route::post('/sales/{saleId}/items', [SaleController::class, 'addItems']);
        Route::post('/sales/{saleId}/confirm', [InvoiceController::class, 'confirmSale']);
    });

});
