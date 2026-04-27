<?php

namespace App\Http\Controllers;
use App\Models\Region;
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
        $data = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'region_id'  => 'required|exists:regions,id',
            'date'       => 'required|date',
            'start_time' => 'required',
            'end_time'   => 'required',
        ]);

        // تحقق أن المستخدم سائق
        $driver = User::role('driver')
            ->where('id', $data['user_id'])
            ->first();

        if (!$driver) {
            return response()->json([
                'message' => 'Selected user is not a driver'
            ], 422);
        }

        // 🔥 منع تداخل المهام
        $exists = DistributionTask::where('user_id', $data['user_id'])
            ->whereDate('date', $data['date'])
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_time', [$data['start_time'], $data['end_time']])
                    ->orWhereBetween('end_time', [$data['start_time'], $data['end_time']])
                    ->orWhere(function ($q2) use ($data) {
                        $q2->where('start_time', '<=', $data['start_time'])
                            ->where('end_time', '>=', $data['end_time']);
                    });
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'هذا السائق لديه مهمة بنفس الوقت'
            ], 422);
        }

        DB::beginTransaction();

        try {

            $task = DistributionTask::create([
                'user_id'    => $driver->id,
                'region_id'  => $data['region_id'],
                'date'       => $data['date'],
                'start_time' => $data['start_time'],
                'end_time'   => $data['end_time'],
                'status'     => 'pending',
            ]);

            $stores = Store::where('region_id', $data['region_id'])->get();

            $attachData = $stores->mapWithKeys(fn ($store) => [
                $store->id => [
                    'visited'    => false,
                    'visited_at' => null,
                ]
            ])->toArray();

            $task->stores()->attach($attachData);

            DB::commit();

            return response()->json([
                'message' => 'Mission created successfully',
                'task_id' => $task->id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error creating mission',
                'error' => $e->getMessage()
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
        $task = DistributionTask::with(['user', 'region', 'stores'])
            ->findOrFail($id);

        $task->stores = $task->stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'lat' => $store->lat,
                'lng' => $store->lng,
                'visited' => $store->pivot->visited,
            ];
        });

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

        $now = now();

        $start = \Carbon\Carbon::parse($task->date . ' ' . $task->start_time);
        $end   = \Carbon\Carbon::parse($task->date . ' ' . $task->end_time);

        // 🔴 منع التعديل إذا المهمة بدأت أو انتهت
        if ($task->status === 'in_progress' || $now->gte($start)) {
            return response()->json([
                'message' => 'لا يمكن تعديل المهمة بعد بدء وقتها'
            ], 403);
        }

        $data = $request->validate([
            'user_id'    => 'sometimes|exists:users,id',
            'region_id'  => 'sometimes|exists:regions,id',
            'date'       => 'sometimes|date',
            'start_time' => 'sometimes',
            'end_time'   => 'sometimes',
        ]);

        // تحقق من السائق
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

        // 🔥 منع التداخل
        if (
            isset($data['user_id']) ||
            isset($data['date']) ||
            isset($data['start_time']) ||
            isset($data['end_time'])
        ) {

            $userId = $data['user_id'] ?? $task->user_id;
            $date   = $data['date'] ?? $task->date;
            $startT = $data['start_time'] ?? $task->start_time;
            $endT   = $data['end_time'] ?? $task->end_time;

            $exists = DistributionTask::where('user_id', $userId)
                ->where('id', '!=', $task->id)
                ->whereDate('date', $date)
                ->where(function ($q) use ($startT, $endT) {
                    $q->whereBetween('start_time', [$startT, $endT])
                        ->orWhereBetween('end_time', [$startT, $endT])
                        ->orWhere(function ($q2) use ($startT, $endT) {
                            $q2->where('start_time', '<=', $startT)
                                ->where('end_time', '>=', $endT);
                        });
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'لا يمكن التعديل، يوجد مهمة بنفس الوقت'
                ], 422);
            }
        }

        DB::beginTransaction();

        try {

            $task->update($data);

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



    /**
     * عرض المهام اليومية
     */
    public function todayTasks()
    {
        $tasks = DistributionTask::with(['user', 'region', 'stores'])
            ->whereDate('date', now()->toDateString())
            ->get()
            ->map(function ($task) {

                $stores = $task->stores->map(function ($store) {
                    return [
                        'id' => $store->id,
                        'name' => $store->name,
                        'lat' => $store->lat,
                        'lng' => $store->lng,
                        'visited' => $store->pivot->visited,
                    ];
                });

                return [
                    'id' => $task->id,
                    'date' => $task->date,
                    'time' => $task->start_time . ' - ' . $task->end_time,
                    'status' => $task->status,
                    'driver' => $task->user->name,
                    'region' => $task->region->name,
                    'stores_count' => $stores->count(),
                    'visited_count' => $stores->where('visited', true)->count(),
                    'stores' => $stores
                ];
            });

        return response()->json($tasks);
    }


    /**
     * عرض المهام اليومية لسائق معين
     */


    public function driverTodayTasks($driverId)
    {
        $tasks = DistributionTask::with(['region', 'stores'])
            ->where('user_id', $driverId)
            ->whereDate('date', now()->toDateString())
            ->get()
            ->map(function ($task) {

                $stores = $task->stores->map(function ($store) {
                    return [
                        'id' => $store->id,
                        'name' => $store->name,
                        'lat' => $store->lat,
                        'lng' => $store->lng,
                        'visited' => $store->pivot->visited,
                    ];
                });

                return [
                    'id' => $task->id,
                    'date' => $task->date,
                    'time' => $task->start_time . ' - ' . $task->end_time,
                    'status' => $task->status,
                    'region' => $task->region->name,
                    'stores_total' => $stores->count(),
                    'visited' => $stores->where('visited', true)->count(),
                    'stores' => $stores
                ];
            });

        return response()->json($tasks);
    }

    /////////////////////////////////////////////////////////
    ///عرض المهمة الحالية للسائق
    public function myTodayTask()
    {
        $user = auth()->user();

        $tasks = DistributionTask::with(['region', 'stores'])
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->orderBy('start_time')
            ->get();

        $now = now();
        $currentTask = null;

        foreach ($tasks as $task) {

            $start = \Carbon\Carbon::parse($task->date . ' ' . $task->start_time);
            $end   = \Carbon\Carbon::parse($task->date . ' ' . $task->end_time);

            // 🔴 انتهى الوقت
            if ($now->gt($end) && $task->status !== 'completed') {
                $task->update(['status' => 'failed']);
                continue;
            }

            // 🟡 ضمن الوقت
            if ($now->between($start, $end) && $task->status === 'pending') {
                $task->update(['status' => 'in_progress']);
            }

            // 🟢 المهمة الحالية
            if (
                $now->between($start, $end) &&
                in_array($task->status, ['pending', 'in_progress'])
            ) {

                $currentTask = [
                    'id' => $task->id,
                    'region' => $task->region->name,
                    'region_lat' => $task->region->lat ?? null,
                    'region_lng' => $task->region->lng ?? null,
                    'time' => $task->start_time . ' - ' . $task->end_time,
                    'status' => $task->status,

                    'stores' => $task->stores->map(function ($store) {
                        return [
                            'id' => $store->id,
                            'name' => $store->name,
                            'lat' => $store->lat,
                            'lng' => $store->lng,
                            'visited' => $store->pivot->visited,
                        ];
                    }),
                ];

                break;
            }
        }

        return response()->json([
            'current_task' => $currentTask,
            'message' => $currentTask ? null : 'No current task'
        ]);
    }



























/////بدء المهمة
    public function startTask($id, Request $request)
    {
        $task = DistributionTask::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $now = now();

        $start = \Carbon\Carbon::parse($task->date . ' ' . $task->start_time);
        $end   = \Carbon\Carbon::parse($task->date . ' ' . $task->end_time);

        if ($now->lt($start)) {
            return response()->json(['message' => 'Too early'], 400);
        }

        if ($now->gt($end)) {
            return response()->json(['message' => 'Task expired'], 400);
        }

        if ($task->status === 'in_progress') {
            return response()->json(['message' => 'Task already started'], 400);
        }

        $task->update(['status' => 'in_progress']);

        return response()->json(['message' => 'Task started']);
    }

///زيارة محل
   /* public function visitStore($taskId, $storeId, Request $request)
    {
        $task = DistributionTask::where('id', $taskId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $now = now();
        $end = \Carbon\Carbon::parse($task->date . ' ' . $task->end_time);

        // إذا انتهى الوقت → فشل المهمة مباشرة
        if ($now->gt($end)) {
            $task->update(['status' => 'failed']);

            return response()->json([
                'message' => 'Task expired and marked as failed'
            ], 400);
        }

        if ($task->status !== 'in_progress') {
            return response()->json([
                'message' => 'Task not started yet'
            ], 400);
        }

        $store = $task->stores()
            ->where('stores.id', $storeId)
            ->first();

        if (!$store) {
            return response()->json([
                'message' => 'Store not found in this task'
            ], 404);
        }

        if ($store->pivot->visited) {
            return response()->json([
                'message' => 'Already visited'
            ], 400);
        }

        $task->stores()->updateExistingPivot($storeId, [
            'visited' => true,
            'visited_at' => now(),
        ]);

        // إذا انتهت كل المحلات → تصبح جاهزة للإنهاء
        $remaining = $task->stores()->wherePivot('visited', false)->exists();

        if (!$remaining) {
            $task->update(['status' => 'ready_to_complete']);
        }

        return response()->json([
            'message' => 'Store visited'
        ]);
    }*/
///إنهاء المهمة
    public function completeTask($id, Request $request)
    {
        $task = DistributionTask::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $now = now();
        $end = \Carbon\Carbon::parse($task->date . ' ' . $task->end_time);

        if (!in_array($task->status, ['in_progress', 'ready_to_complete'])) {
            return response()->json([
                'message' => 'Task not allowed to complete'
            ], 400);
        }

        if ($now->gt($end)) {
            $task->update(['status' => 'failed']);

            return response()->json([
                'message' => 'Task expired and marked as failed'
            ], 400);
        }

        // ✅ تحقق من زيارة كل المحلات
        $unvisited = $task->stores()
            ->wherePivot('visited', false)
            ->exists();

        if ($unvisited) {
            return response()->json([
                'message' => 'You must visit all stores before completing the task'
            ], 400);
        }

        // ✅ تحقق من وجود مبيعات
        $hasSales = \App\Models\Sale::where('distribution_task_id', $task->id)
            ->where('status', 'confirmed')
            ->exists();

        if (!$hasSales) {
            return response()->json([
                'message' => 'لا يمكن إنهاء المهمة بدون مبيعات'
            ], 400);
        }

        $task->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Task completed'
        ]);
    }


///عرض المهام اليومية من قبل  السائق
    public function myDailyTasks()
    {
        $user = auth()->user();

        $tasks = DistributionTask::with(['region', 'stores'])
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->orderBy('start_time')
            ->get()
            ->map(function ($task) {

                return [
                    'id' => $task->id,
                    'date' => $task->date,
                    'time' => $task->start_time . ' - ' . $task->end_time,
                    'status' => $task->status,
                    'region' => $task->region->name,
                    //'lat' => $task->region->lat ?? null,
                    //'lng' => $task->region->lng ?? null,

                    'stores' => $task->stores->map(function ($store) {
                        return [
                            'id' => $store->id,
                            'name' => $store->name,
                            'lat' => $store->lat,
                            'lng' => $store->lng,
                            'visited' => $store->pivot->visited,
                        ];
                    }),
                ];
            });

        return response()->json($tasks);
    }

//عرض المحلات للسائق
    public function myStores()
    {
        $user = auth()->user();

        $tasks = DistributionTask::with(['stores'])
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->get();

        $stores = [];

        foreach ($tasks as $task) {
            foreach ($task->stores as $store) {
                $stores[] = [
                    'task_id' => $task->id,
                    'store_id' => $store->id,
                    'name' => $store->name,
                    'type' => $store->type,
                    'lat' => $store->lat,
                    'lng' => $store->lng,
                    'visited' => $store->pivot->visited,
                ];
            }
        }

        return response()->json([
            'data' => $stores
        ]);
    }



}
