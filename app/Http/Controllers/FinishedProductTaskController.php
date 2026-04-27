<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\FinishedProductTaskService;
use Illuminate\Support\Facades\Auth;

class FinishedProductTaskController extends Controller
{
    protected $service;

    public function __construct(FinishedProductTaskService $service)
    {
        $this->service = $service;
    }

    // Admin creates a send task
     public function createSendTask(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'driver_id' => 'required|integer|exists:users,id',
            'items' => 'required|array',
            'items.*.finished_product_id' => 'required|integer|exists:finished_products,id',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        // ✅ تحقق من أمين المستودع
        $user = User::find($request->user_id);
        if (!$user || !$user->hasRole('product_storekeeper')) {
            return response()->json([
                'message' => 'Selected user must be a product storekeeper'
            ], 422);
        }

        // ✅ تحقق من السائق
        $driver = User::find($request->driver_id);
        if (!$driver || !$driver->hasRole('driver')) {
            return response()->json([
                'message' => 'Selected driver is invalid'
            ], 422);
        }

        try {
            $task = $this->service->createSendTask(
                Auth::id(),              // admin
                $request->user_id,       // storekeeper
                $request->driver_id,
                $request->items
            );

            return response()->json([
                'message' => 'Send task created successfully',
                'task' => $task
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating task',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    // ✅ Warehouse confirms sending
    public function confirmSend(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user->hasRole('product_storekeeper')) {
            return response()->json([
                'message' => 'Only product storekeeper can confirm send'
            ], 403);
        }

        try {
            $allocations = $this->service->confirmSend($id, $user->id);

            return response()->json([
                'message' => 'Products sent successfully',
                'allocations' => $allocations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Send failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    // Display production employee tasks for admin and production employee
    public function getTasks(Request $request)
    {
        $user = Auth::user();
        $userId = null;

        // إذا كان المستخدم موظف إنتاج، عرض مهامه فقط
        if ($user->hasRole('product_storekeeper')) {
            $userId = $user->id;
        }
        // الـ admin والـ super_admin يمكنهم رؤية جميع المهام

        $tasks = $this->service->getProductionEmployeeTasks($userId);

        return response()->json([
            'message' => 'product storekeeper tasks retrieved successfully',
            'data' => $tasks,
            'count' => count($tasks)
        ], 200);
    }
}
