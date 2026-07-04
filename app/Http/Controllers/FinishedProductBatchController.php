<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FinishedProductBatchService;

class FinishedProductBatchController extends Controller
{
    protected $service;

    public function __construct(FinishedProductBatchService $service)
    {
        $this->service = $service;
    }public function create(Request $request)
{
    $request->validate([
        'finished_product_id' => 'required|integer|exists:finished_products,id',
        'production_order_id' => 'required|integer|exists:production_orders,id',
        'quantity' => 'required|numeric|min:0.01',
        'production_date' => 'required|date',
        'expiry_type' => 'required|in:month,year',
        'expiry_value' => 'required|integer|min:1'
    ]);

    $batch = $this->service->createBatch(
        $request->finished_product_id,
        $request->production_order_id,
        $request->quantity,
        $request->production_date,
        $request->expiry_type,
        $request->expiry_value
    );

    return response()->json([
        'message' => 'Batch created',
        'batch' => $batch
    ], 201);
}

    public function list1($orderId)
    {
        return response()->json(
            $this->service->getBatchesByOrder($orderId)
        );
    }


public function send($id) {
    try {
        $batch = $this->service->sendBatch($id);
        return response()->json(['message' => 'تم إرسال الدفعة بنجاح', 'batch' => $batch], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'حدث خطأ أثناء الإرسال'], 500);
    }
}

public function receive($id) {
    try {
        $batch = $this->service->receiveBatch($id);
        return response()->json(['message' => 'تم استلام الدفعة وإضافتها للمخزون', 'batch' => $batch], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'حدث خطأ أثناء الاستلام'], 500);
    }
}


public function listAllSentMaterials(Request $request)
{
    
 

    // طلب البيانات المنسقة من السيرفس
    $data = $this->service->getFormattedProductionTasks();

    return response()->json([
        'message' => 'All materials retrieved successfully',
        'data' => $data
    ]);
}


public function receiveTasks()
{
    return response()->json([
        'message' => 'Tasks fetched successfully',
        'data' => $this->service->getTasksForReceive()
    ]);
}
}
