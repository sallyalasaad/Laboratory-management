<?php

use App\Http\Controllers\FinishedProductReceiveController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SalesReportController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProductionStageController;
use App\Http\Controllers\FinishedProductBatchController;
use App\Http\Controllers\FinishedProductTaskController;
use App\Http\Controllers\FinishedProductWarehouseController;
use App\Http\Controllers\RawMaterialTaskController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\DistributionTaskController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\ProfitController;

//////////////////////////////////////////////////////////

Route::get('/forecast', [ForecastController::class, 'forecast'])
    ->middleware(['auth', 'role:admin|accountant']);

Route::get('/forecast/saved/{month}', [ForecastController::class, 'showSavedForecast'])
    ->middleware(['auth', 'role:admin|accountant']);
// تسجيل الدخول
//////////////////////////////////////////////////////////

Route::post('/login', [AuthController::class,'login']);
Route::post('/password/forgot', [AuthController::class,'sendResetPasswordOtp']);
Route::post('/password/reset', [AuthController::class,'resetPassword']);

//////////////////////////////////////////////////////////
// Admin + Super Admin
//////////////////////////////////////////////////////////

Route::middleware(['auth:sanctum', 'role:admin|super_admin'])->group(function () {

    Route::post('/employee', [AdminController::class,'createEmployee']);
    Route::get('/employees', [AdminController::class,'employees']);
    Route::put('/employees/{id}', [AdminController::class,'updateEmployee']);
    Route::delete('/user/{id}', [AdminController::class,'deleteUser']);
    Route::patch('/user/{id}/toggle', [AdminController::class,'toggleVerify']);
});

//////////////////////////////////////////////////////////
// فقط Super Admin
//////////////////////////////////////////////////////////

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

//////////////////////////////////////////////////////////
// Raw material tasks: create by admin, handled by warehouse user
//////////////////////////////////////////////////////////

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/tasks/raw-materials/receive', [RawMaterialTaskController::class, 'createReceiveTask']);
    Route::post('/tasks/raw-materials/send', [RawMaterialTaskController::class, 'createSendTask']);

    // warehouse endpoints
    Route::get('/tasks/raw-materials', [RawMaterialTaskController::class, 'listTasks']);

    Route::middleware(['role:admin|super_admin'])
        ->get('/tasks/raw-materials/admin', [RawMaterialTaskController::class, 'adminListTasks']);

    // Warehouse: submit receive input (does not change inventory yet)
    Route::post('/tasks/raw-materials/{id}/submit-receive-input', [RawMaterialTaskController::class, 'submitReceiveInput']);

    // Warehouse: confirm the receive to actually update inventory
    Route::post('/tasks/raw-materials/{id}/confirm-receive', [RawMaterialTaskController::class, 'confirmReceive']);
    Route::post('/tasks/raw-materials/{id}/confirm-send', [RawMaterialTaskController::class, 'confirmSend']);

    // inventory summary
    Route::middleware(['role:admin|raw_storekeeper|accountant'])->get('/inventory/summary', [RawMaterialTaskController::class, 'inventorySummary']);

    // Notes for raw material tasks
    Route::middleware([ 'role:raw_storekeeper|product_storekeeper|accountant|production_employee|driver'])->post('/tasks/raw-materials/notes', [RawMaterialTaskController::class, 'addNote']);

    Route::middleware(['role:admin|super_admin'])
        ->get('/tasks/raw-materials/notes', [RawMaterialTaskController::class, 'adminListNotes']);

    Route::middleware(['role:admin|super_admin'])
        ->patch('/tasks/raw-materials/notes/{noteId}/mark-read', [RawMaterialTaskController::class, 'markNoteRead']);

    Route::middleware(['role:admin|super_admin'])
        ->delete('/tasks/raw-materials/{id}/notes/delete-read', [RawMaterialTaskController::class, 'deleteReadNotes']);

        // عرض المواد الأولية المؤكد إرسالها لموظف الإنتاج
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/production/confirmed-sent-materials', [RawMaterialTaskController::class, 'listConfirmedSentMaterials']);

    Route::middleware('role:production_employee|admin|super_admin')
        ->post('/production/tasks/{id}/confirm-receive', [RawMaterialTaskController::class, 'confirmReceiveinp']);

    // عرض المواد الأولية المؤكد استلامها من قبل موظف الإنتاج
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/production/received-materials', [RawMaterialTaskController::class, 'listProductionConfirmedMaterials']);

});

//////////////////////////////////////////////////////////
// notes for production
//////////////////////////////////////////////////////////

Route::middleware(['auth:sanctum'])->group(function () {

    // إرسال ملاحظة
    Route::post('/notes/send', [ProductionOrderController::class, 'sendNote']);

    // عرض كل الملاحظات
    Route::get('/notes', [ProductionOrderController::class, 'notes']);

    // عدد غير المقروء
    Route::get('/notes/unread', [ProductionOrderController::class, 'unreadNotes']);

    // تعليم كمقروءة
    Route::patch('/notes/{id}/read', [ProductionOrderController::class, 'markAsRead']);

    // حذف
    Route::delete('/notes/{id}', [ProductionOrderController::class, 'deleteNote']);
});

//////////////////////////////////////////////////////////
// طلبات الإنتاج
//////////////////////////////////////////////////////////

Route::middleware(['auth:sanctum'])->group(function () {

    // إنشاء طلب إنتاج (Admin فقط)
    Route::middleware('role:admin|super_admin')
        ->post('/production-orders', [ProductionOrderController::class, 'create']);

    // عرض طلبات الإنتاج (موظف الإنتاج + الإدارة)
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/production-orders', [ProductionOrderController::class, 'currentTasks']);

    //عرض سجل الطلبات
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/orders-history', [ProductionOrderController::class, 'ordersHistory']);

    //عرض كل الطلبات
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/all-orders', [ProductionOrderController::class, 'allorders']);

    //عرض طلب معين
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/order/{id}', [ProductionOrderController::class, 'show']);

    // بدء الطلب
    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/start', [ProductionStageController::class, 'startOrder']);

    // قبول طلب الإنتاج
    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/accept', [ProductionStageController::class,'acceptOrder']);

    // رفض الطلب
    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/reject', [ProductionStageController::class,'rejectOrder']);

    // إيقاف الطلب
    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/pause', [ProductionStageController::class, 'pauseOrder']);

    // استئناف الطلب
    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/resume', [ProductionStageController::class, 'resumeOrder']);

    // إنهاء مرحلة
    Route::middleware('role:production_employee')
        ->post('/production-stages/{stageId}/complete', [ProductionStageController::class, 'completeStage']);

    //إيقاف مرحلة



    Route::middleware('role:production_employee')->
    post('/stages/{stageId}/pause', [ProductionStageController::class, 'pauseStage']);

    //استئناف مرحلة


    Route::middleware('role:production_employee')->
    post('/stages/{stageId}/resume', [ProductionStageController::class, 'resumeStage']);






    // إنشاء Batch للمنتج النهائي
    Route::middleware('role:production_employee')
        ->post('/finished-product-batches', [FinishedProductBatchController::class, 'create']);

    // عرض Batches
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/production-orders/{orderId}/batches', [FinishedProductBatchController::class, 'list1']);

    Route::middleware('role:production_employee')
        ->get('/production/incoming', [ProductionOrderController::class, 'incomingTasks']);
});

//////////////////////////////////////////////////////////
// عرض stages
//////////////////////////////////////////////////////////

Route::middleware(['auth:sanctum'])
    ->get('/production-orders/{orderId}/stages', [ProductionOrderController::class, 'listOrders']);

//////////////////////////////////////////////////////////
// Finished Product Tasks
//////////////////////////////////////////////////////////

Route::middleware(['auth:sanctum'])->group(function () {

    // Admin creates send task
    Route::middleware(['role:admin|super_admin'])
        ->post('/finished-product-tasks/send', [FinishedProductTaskController::class, 'createSendTask']);

    // Display product_storekeeper tasks for admin and production employee only
    Route::middleware(['role:admin|super_admin|product_storekeeper'])
        ->get('/finished-product-tasks', [FinishedProductTaskController::class, 'getTasks']);

    // Warehouse keeper confirms sending
    Route::middleware(['role:product_storekeeper|admin|super_admin'])
        ->post('/finished-product-tasks/{id}/confirm-send', [FinishedProductTaskController::class, 'confirmSend']);
    // Warehouse keeper confirms receiving
    Route::middleware(['role:driver|admin|super_admin'])
        ->post('/finished-product-tasks/{id}/receive', [FinishedProductReceiveController::class, 'confirmReceive']);

});

//////////////////////////////////////////////////////////
// Finished Product Warehouse (Inventory)
//////////////////////////////////////////////////////////

Route::middleware(['auth:sanctum', 'role:admin|super_admin|product_storekeeper'])->group(function () {

    // Display all finished product batches in FIFO order
    Route::get('/finished-product-batches', [FinishedProductWarehouseController::class, 'listFinishedProductBatches']);

    // Display finished products list
    Route::get('/finished-products-list', [FinishedProductWarehouseController::class, 'listFinishedProducts']);


    // Display product details with batch information
    Route::get('/finished-products/{productId}/details', [FinishedProductWarehouseController::class, 'getProductDetails']);

    // Get returned items from drivers
    Route::get('/returned-items', [FinishedProductWarehouseController::class, 'getReturnedItems']);

    // Display expired finished product batches (اتلاف)
    Route::get('/finished-products/expired', [FinishedProductWarehouseController::class, 'expired']);

    // Accept returned item
    Route::post('/warehouse/returns/accept-all/{driverId}', [FinishedProductWarehouseController::class, 'acceptReturnedItem']);

});

Route::middleware(['auth:sanctum', 'role:admin|super_admin|product_storekeeper|accountant'])->group(function () {


// Display all finished products in warehouse
     Route::get('/finished-products', [FinishedProductWarehouseController::class, 'getAllFinishedProducts']);



});
//////////////////////////////////////////////////////////
// Distribution Tasks
//////////////////////////////////////////////////////////

Route::middleware(['auth:sanctum', 'role:admin|super_admin'])
    ->prefix('distribution-tasks')
    ->group(function () {

        // إنشاء مهمة توزيع
        Route::post('/', [DistributionTaskController::class, 'store']);

        //عرض السائقين

        //عرض المناطق
        Route::get('/regions', [RegionController::class, 'index']);


        //عرض المهام اليومية
        Route::get('/today', [DistributionTaskController::class, 'todayTasks']);
        // تفاصيل مهمة
        Route::get('/{id}', [DistributionTaskController::class, 'show']);

        // عرض كل المهام
        Route::get('/', [DistributionTaskController::class, 'index']);

        //تعديل مهمة
        Route::put('/{id}', [DistributionTaskController::class, 'update']);

        //مهام سائق معين
        Route::get('/driver/{id}', [DistributionTaskController::class, 'driverTasks']);


        //عرض المهام اليومية لسائق معين
        Route::get('/driver/{driverId}/today', [DistributionTaskController::class, 'driverTodayTasks']);


//عرض مخزون سائق معين

        Route::get('/driver-stock/{driverId}', [ReturnController::class, 'driverStock']);

       //عرض البصاعة المرتجعة والمستلمة
        Route::get('/driver-stock-report/{driverId}', [ReturnController::class, 'driverStockReport']);

//عرض المبيعات اليومية لسائق معين
                Route::post('/reports/daily-sales', [SalesReportController::class, 'daily']);

                //عرض المبيعات الشهرية لسائق معين
        Route::post('/reports/monthly-sales', [SalesReportController::class, 'monthly']);
    });
    Route::middleware(['auth:sanctum', 'role:accountant|admin|super_admin'])
     ->group(function () {
                Route::get('/drivers', [DistributionTaskController::class, 'drivers']);
                //عرض الفواتير الشهرية لسائق معين

        Route::get('/invoices/monthly/{driverId}/{month}', [InvoiceController::class, 'monthly']);

//عرض الفواتير اليومية لسائق معين
        Route::get('/invoices/daily/{driverId}', [InvoiceController::class, 'daily']);
});
//////////////////////////////////////////////////////////
// Driver
//////////////////////////////////////////////////////////

Route::middleware(['auth:sanctum','role:admin|super_admin|driver' ])->group(function () {

    // عرض المهمة الحالية
    Route::get('/my-tasks/today', [DistributionTaskController::class, 'myTodayTask']);

    // عرض المهام اليومية
    Route::get('/my-tasks/daily', [DistributionTaskController::class, 'myDailyTasks']);

    // بدء المهمة
    Route::post('/my-tasks/{id}/start', [DistributionTaskController::class, 'startTask']);

    // زيارة محل
    Route::post('/my-tasks/{taskId}/store/{storeId}/visit', [DistributionTaskController::class, 'visitStore']);

    // إنهاء المهمة
    Route::post('/my-tasks/{id}/complete', [DistributionTaskController::class, 'completeTask']);

    //////////////////////////////////////////////////////////
    // Sales
    //////////////////////////////////////////////////////////

    Route::post('/scan-store', [StoreController::class, 'scanStore']);

    Route::post('/sales', [SaleController::class, 'createSale']);

    Route::post('/sales/{saleId}/items', [SaleController::class, 'addItems']);

    Route::post('/sales/{saleId}/confirm', [InvoiceController::class, 'confirmSale']);
///عرض المنتجات مع السائق

    Route::get('/my-stock', [SaleController::class, 'myStock']);

    //عرض المحلات للسائق

    Route::get('/my-stores', [DistributionTaskController::class, 'myStores']);
//مرتجعات
    Route::post('/car-stock/return', [ReturnController::class, 'autoReturn']);

    //عرض مخزون السيارة
    Route::get('/car-stock', [ReturnController::class, 'myCarStock']);

    //عرض الفواتير
    Route::get('/invoices', [InvoiceController::class, 'index']);
    // عرض فاتورة واحدة
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    // عرض المواد لتأكيد استلامها من السائق
    Route::get(
        '/finished-products/tasks/preview-receive',
        [FinishedProductReceiveController::class, 'showReceiveItems']
    );








});
Route::middleware(['auth:sanctum'])->group(function () {

    // 1. موظف الإنتاج: يقوم بإرسال الدفعة (تغيير الحالة إلى 'sent')
    // يتم التقييد بصلاحية موظف الإنتاج فقط
    Route::middleware('role:production_employee')
        ->post('/finished-product-batches/{id}/send', [FinishedProductBatchController::class, 'send']);

    // 2. موظف المستودع الجاهز: يقوم باستلام الدفعة (تغيير الحالة إلى 'received' وتفعيل الكمية)
    // يتم التقييد بصلاحية موظف المستودع أو المدير
    Route::middleware('role:product_storekeeper|admin|super_admin')
        ->post('/finished-product-batches/{id}/receive', [FinishedProductBatchController::class, 'receive']);
       Route::middleware('role:product_storekeeper|admin|super_admin')
        ->get('/finished-product-tasks/receive-tasks',
[FinishedProductBatchController::class, 'receiveTasks']);

});

Route::middleware(['auth:sanctum'])->group(function () {
    //عرض تصفية سائق معين
 Route::middleware('role:accountant|admin|super_admin')
 ->get('/settlement/summary/{driverId}', [App\Http\Controllers\SettlementController::class, 'getSummary']);
 //انهاء  تصفية
 Route::middleware('role:accountant|admin|super_admin')
 ->post('/settlement/finalize/{driverId}', [App\Http\Controllers\SettlementController::class, 'finalize']);

//عرض لكل السائقين تصفية
 Route::middleware('role:accountant|admin|super_admin')
 ->get('/settlements/all', [App\Http\Controllers\SettlementController::class, 'index']);

//عرض مخازن السائقين
  Route::middleware('role:accountant|admin|super_admin')
 ->get('/inventory/all-drivers', [App\Http\Controllers\ReturnController::class, 'allDriversStocks']);
//عرض المبيعات الشهرية
  Route::middleware('role:accountant|admin|super_admin')
 ->get('/sales/monthly/all', [SalesReportController::class, 'allDriversMonthly']);
//الربح الشهري
       Route::middleware('role:accountant|admin|super_admin')
 ->post('/reports/profit', [ProfitController::class, 'getReport']);

});


