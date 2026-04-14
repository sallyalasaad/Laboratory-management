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

        $driver = User::find($request->driver_id);
        if (!$driver || !$driver->hasRole('driver')) {
            return response()->json(['message' => 'Selected driver is invalid or not assigned the driver role'], 422);
        }

        try {
            $task = $this->service->createSendTask(
                Auth::id(),
                $request->user_id,
                $request->driver_id,
                $request->items
            );

            return response()->json(['message' => 'Send task created', 'task' => $task], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating task', 'error' => $e->getMessage()], 422);
        }
    }

    // Warehouse keeper confirms sending
    public function confirmSend(Request $request, $id)
    {
        $user = Auth::user();

        try {
            $allocations = $this->service->confirmSend($id);

            return response()->json([
                'message' => 'Materials sent successfully',
                'allocations' => $allocations
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Allocation failed', 'error' => $e->getMessage()], 422);
        }
    }
}
