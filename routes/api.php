<?php

use App\Http\Controllers\FinishedProductBatchController;
use App\Http\Controllers\ProductionStageController;
use App\Http\Controllers\RawMaterialTaskController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductionOrderController;
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
    Route::middleware(['role:admin|super_admin'])->get('/tasks/raw-materials/admin', [\App\Http\Controllers\RawMaterialTaskController::class, 'adminListTasks']);
    // Warehouse: submit receive input (does not change inventory yet)
    Route::post('/tasks/raw-materials/{id}/submit-receive-input', [\App\Http\Controllers\RawMaterialTaskController::class, 'submitReceiveInput']);

    // Warehouse: confirm the receive to actually update inventory
    Route::post('/tasks/raw-materials/{id}/confirm-receive', [\App\Http\Controllers\RawMaterialTaskController::class, 'confirmReceive']);
    Route::post('/tasks/raw-materials/{id}/confirm-send', [\App\Http\Controllers\RawMaterialTaskController::class, 'confirmSend']);

    // inventory summary
    Route::get('/inventory/summary', [\App\Http\Controllers\RawMaterialTaskController::class, 'inventorySummary']);

    // Notes for raw material tasks
    Route::post('/tasks/raw-materials/notes', [\App\Http\Controllers\RawMaterialTaskController::class, 'addNote']);
    Route::middleware(['role:admin|super_admin'])->get('/task/raw-materials/notes', [\App\Http\Controllers\RawMaterialTaskController::class, 'adminListNotes']);
    Route::middleware(['role:admin|super_admin'])->patch('/tasks/raw-materials/notes/{noteId}/mark-read', [\App\Http\Controllers\RawMaterialTaskController::class, 'markNoteRead']);
    Route::middleware(['role:admin|super_admin'])->delete('/tasks/raw-materials/{id}/notes/delete-read', [\App\Http\Controllers\RawMaterialTaskController::class, 'deleteReadNotes']);
});

///notes for production
Route::middleware(['auth:sanctum'])->group(function () {

    Route::middleware('role:production_employee|admin|super_admin')
->post('/production-notes', [ProductionOrderController::class, 'addProductionNote']);
    Route::middleware('role:production_employee')
        ->post('/production/notes/general', [ProductionOrderController::class, 'addGeneralNote']);

    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/production-notes/{id}', [ProductionOrderController::class, 'getProductionNotes']);

    Route::middleware('role:production_employee|admin|super_admin')
        ->patch('/notes/read/{id}', [ProductionOrderController::class, 'markAsRead']);

    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/production-notes-unread/{id}', [ProductionOrderController::class, 'getUnreadCount']);


    Route::middleware('role:production_employee|admin|super_admin')->delete('/notes/{id}', [ProductionOrderController::class, 'deleteNote']);





});


Route::middleware(['auth:sanctum'])->group(function () {

    // إنشاء طلب إنتاج (Admin فقط)
    Route::middleware('role:admin|super_admin')
        ->post('/production-orders', [ProductionOrderController::class, 'create']);

    // عرض طلبات الإنتاج (موظف الإنتاج + الإدارة)
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/production-orders', [ProductionOrderController::class, 'currentTasks']);
//عرض سجل الطلبات
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/ordersHistory', [ProductionOrderController::class, 'ordersHistory']);
    //عرض كل الطلبات
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/allorders', [ProductionOrderController::class, 'allorders']);
    ///عرص طلب معين
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/order/{id}', [ProductionOrderController::class, 'show']);

    // بدء الطلب
    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/start', [ProductionStageController::class, 'startOrder']);
// قبول طلب الإنتاج
    Route::middleware('role:production_employee')->post('/production-orders/{orderId}/accept',
        [ProductionStageController::class,'acceptOrder']);
    // رفض الطلب
    Route::middleware('role:production_employee')->post('/production-orders/{orderId}/reject',
        [ProductionStageController::class,'rejectOrder']);
    // إيقاف الطلب
    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/pause', [ProductionStageController::class, 'pauseOrder']);

    // استئناف الطلب
    Route::middleware('role:production_employee')
        ->post('/production-orders/{orderId}/resume', [ProductionStageController::class, 'resumeOrder']);

    // إنهاء مرحلة
    Route::middleware('role:production_employee')
        ->post('/production-stages/{stageId}/complete', [ProductionStageController::class, 'completeStage']);

    // إنشاء Batch للمنتج النهائي
    Route::middleware('role:production_employee')
        ->post('/finished-product-batches', [FinishedProductBatchController::class, 'create']);

    // عرض Batches
    Route::middleware('role:production_employee|admin|super_admin')
        ->get('/production-orders/{orderId}/batches', [FinishedProductBatchController::class, 'list1']);
    Route::middleware('role:production_employee')->get('/production/incoming', [ProductionOrderController::class, 'incomingTasks']);
});
// عرض طلبات الإنتاج stages
Route::middleware(['auth:sanctum'])->get(
    '/production-orders/{orderId}/stages',
    [ProductionOrderController::class, 'listOrders']
);

// عرض مهام الإنتاج
/*Route::middleware('role:production_employee|admin|super_admin')
    ->get('/production/tasks', [RawMaterialTaskController::class, 'productionTasks']);*/

// تأكيد الاستلام من قبل الإنتاج
Route::middleware('role:production_employee|admin|super_admin')
    ->post('/production/tasks/{id}/confirm-receive',
        [RawMaterialTaskController::class, 'confirmReceiveFromProduction']
    );
