<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Store;
use App\Models\DistributionTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DistributionTaskController extends Controller
{
    /**
     * إنشاء مهمة توزيع وربطها بالسائق والمحلات
     */
    public function store(Request $request)
    {
        // 1. Validation
        $data = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'region_id'  => 'required|exists:regions,id',
            'date'       => 'required|date',
            'start_time' => 'required',
            'end_time'   => 'required',
        ]);

        // 2. التأكد أن المستخدم Driver (Spatie Roles)
        $driver = User::role('driver')
            ->where('id', $data['user_id'])
            ->first();

        if (!$driver) {
            return response()->json([
                'message' => 'Selected user is not a driver'
            ], 422);
        }

        DB::beginTransaction();

        try {

            // 3. إنشاء المهمة
            $task = DistributionTask::create([
                'user_id'    => $driver->id,
                'region_id'  => $data['region_id'],
                'date'       => $data['date'],
                'start_time' => $data['start_time'],
                'end_time'   => $data['end_time'],
                'status'     => 'pending',
            ]);

            // 4. جلب محلات المنطقة
            $stores = Store::where('region_id', $data['region_id'])->get();

            // 5. تجهيز بيانات الربط (Pivot)
            $attachData = $stores->mapWithKeys(function ($store) {
                return [
                    $store->id => [
                        'visited'    => false,
                        'visited_at' => null,
                    ]
                ];
            })->toArray(); // 🔥 مهم جداً

            // 6. ربط المحلات بالمهمة
            $task->stores()->attach($attachData);

            DB::commit();

            return response()->json([
                'message'      => 'Mission created successfully',
                'task_id'      => $task->id,
                'driver_id'    => $driver->id,
                'region_id'    => $task->region_id,
                'stores_count' => $stores->count(),
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error creating mission',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب السائقين
     */
    public function drivers()
    {
        return response()->json(
            User::role('driver')->get(['id', 'name'])
        );
    }

    /**
     * عرض مهمة واحدة بالتفاصيل
     */
    public function show($id)
    {
        $task = DistributionTask::with([
            'user',
            'region',
            'stores'
        ])->findOrFail($id);

        return response()->json($task);
    }




    /**
     * عرض كل المهام
     */
    public function index()
    {
        $tasks = DistributionTask::with(['user', 'region', 'stores'])
            ->latest()
            ->get();

        return response()->json($tasks);
    }



    /**
     * تعديل المهمة
     */
    public function update(Request $request, $id)
    {
        $task = DistributionTask::with('stores')->findOrFail($id);

        $data = $request->validate([
            'user_id'    => 'sometimes|exists:users,id',
            'region_id'  => 'sometimes|exists:regions,id',
            'date'       => 'sometimes|date',
            'start_time' => 'sometimes',
            'end_time'   => 'sometimes',
            'status'     => 'sometimes|string',
        ]);

        if (isset($data['user_id'])) {
            $driver = User::role('driver')
                ->where('id', $data['user_id'])
                ->first();

            if (!$driver) {
                return response()->json([
                    'message' => 'Selected user is not a driver'
                ], 422);
            }

            $data['user_id'] = $driver->id;
        }

        DB::beginTransaction();

        try {

            // تحديث بيانات المهمة
            $task->update($data);

            // إذا تغيرت المنطقة نعيد ربط المحلات
            if (isset($data['region_id'])) {

                $stores = Store::where('region_id', $data['region_id'])->get();

                $attachData = $stores->mapWithKeys(fn ($store) => [
                    $store->id => [
                        'visited' => false,
                        'visited_at' => null,
                    ]
                ])->toArray();

                $task->stores()->sync($attachData);
            }

            DB::commit();

            return response()->json([
                'message' => 'Task updated',
                'task' => $task->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * مهام سائق معين
     */
    public function driverTasks($driverId)
    {
        $tasks = DistributionTask::with('stores')
            ->where('user_id', $driverId)
            ->get();

        return response()->json($tasks);
    }


}
