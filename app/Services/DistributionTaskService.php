<?php
namespace App\Services;

use App\DAO\DistributionTaskDAO;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DistributionTaskService
{
    protected $dao;

    public function __construct(DistributionTaskDAO $dao)
    {
        $this->dao = $dao;
    }

    public function createTask($request)
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

        // 🔥 حساب البداية الفعلية (قبل ساعة)
        $newStart = \Carbon\Carbon::parse($data['date'].' '.$data['start_time'])->subHour();
        $newEnd   = \Carbon\Carbon::parse($data['date'].' '.$data['end_time']);

        // 🔥 منع التداخل الحقيقي
        $exists = $this->dao->checkOverlap($data['user_id'], $data['date'], $newStart, $newEnd);

        if ($exists) {
            return response()->json([
                'message' => 'هذا السائق لديه مهمة ضمن نفس الوقت (مع الساعة المبكرة)'
            ], 422);
        }

        DB::beginTransaction();

        try {

            $task = $this->dao->createTask($driver->id, $data);

            $stores = $this->dao->getRegionStores($data['region_id']);

            $attachData = $stores->mapWithKeys(fn ($store) => [
                $store->id => [
                    'visited'    => false,
                    'visited_at' => null,
                ]
            ])->toArray();

            $this->dao->attachStores($task, $attachData);

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
    public function getDrivers()
    {
        return $this->dao->getDrivers();
    }


    public function show($id)
    {
        $task = $this->dao->getTaskById($id);

        $task->stores = $task->stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'lat' => $store->lat,
                'lng' => $store->lng,
                'visited' => $store->pivot->visited,
            ];
        });

        return $task;
    }

    public function index()
    {
        return $this->dao->getAllTasks();
    }




    public function update($request, $id)
    {
        $task = $this->dao->getTaskWithStores($id);

        $now = now();

        $start = \Carbon\Carbon::parse($task->date . ' ' . $task->start_time);
        $end   = \Carbon\Carbon::parse($task->date . ' ' . $task->end_time);

        // 🔴 منع التعديل إذا المهمة بدأت أو انتهت
        if ($task->status === 'in_progress' || $now->gte($start)) {
            return [
                'ok' => false,
                'code' => 403,
                'message' => 'لا يمكن تعديل المهمة بعد بدء وقتها'
            ];
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

            $driver = $this->dao->getDriverById($data['user_id']);

            if (!$driver) {
                return [
                    'ok' => false,
                    'code' => 422,
                    'message' => 'Selected user is not a driver'
                ];
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

            $exists = $this->dao->checkOverlapUpdate(
                $userId,
                $task->id,
                $date,
                $startT,
                $endT
            );

            if ($exists) {
                return [
                    'ok' => false,
                    'code' => 422,
                    'message' => 'لا يمكن التعديل، يوجد مهمة بنفس الوقت'
                ];
            }
        }

        try {

            $this->dao->updateTask($task, $data);

            if (isset($data['region_id'])) {

                $stores = $this->dao->getRegionStores($data['region_id']);

                $attachData = $stores->mapWithKeys(fn ($store) => [
                    $store->id => [
                        'visited' => false,
                        'visited_at' => null,
                    ]
                ])->toArray();

                $this->dao->syncStores($task, $attachData);
            }

            return [
                'ok' => true,
                'task' => $task->fresh()
            ];

        } catch (\Exception $e) {
            return [
                'ok' => false,
                'code' => 500,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ];
        }
    }



    public function driverTasks($driverId)
    {
        return $this->dao->getDriverTasks($driverId);
    }


    public function todayTasks()
    {
        $tasks = $this->dao->getTodayTasks();

        return $tasks->map(function ($task) {

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
    }

    public function driverTodayTasks($driverId)
    {
        $tasks = $this->dao->getDriverTodayTasks($driverId);

        return $tasks->map(function ($task) {

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
    }

    public function myTodayTask($user)
    {
        $tasks = $this->dao->getDriverTodayTasksWithRelations($user->id);

        $now = now();
        $currentTask = null;

        foreach ($tasks as $task) {

            $start = \Carbon\Carbon::parse($task->date . ' ' . $task->start_time);
            $end   = \Carbon\Carbon::parse($task->date . ' ' . $task->end_time);

            $allowedStart = $start->copy()->subHour();

            // 🔴 انتهى الوقت
            if ($now->gt($end) && $task->status !== 'completed') {
                $task->update(['status' => 'failed']);
                continue;
            }

            // 🟡 تحديث الحالة
            if ($now->between($allowedStart, $end) && $task->status === 'pending') {
                $task->update(['status' => 'in_progress']);
            }

            // 🟢 المهمة الحالية
            if (
                $now->between($allowedStart, $end) &&
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

        return [
            'current_task' => $currentTask,
            'message' => $currentTask ? null : 'No current task'
        ];
    }

    public function startTask($id, $request)
    {
        $task = $this->dao->getTaskForDriver($id, $request->user()->id);

        $now = now();

        $start = \Carbon\Carbon::parse($task->date . ' ' . $task->start_time);
        $end   = \Carbon\Carbon::parse($task->date . ' ' . $task->end_time);

        // 🔥 السماح قبل ساعة
        $allowedStart = $start->copy()->subHour();

        if ($now->lt($allowedStart)) {
            return [
                'ok' => false,
                'code' => 400,
                'message' => 'Too early'
            ];
        }

        if ($now->gt($end)) {
            return [
                'ok' => false,
                'code' => 400,
                'message' => 'Task expired'
            ];
        }

        if ($task->status === 'in_progress') {
            return [
                'ok' => false,
                'code' => 400,
                'message' => 'Task already started'
            ];
        }

        $this->dao->updateTaskStatus($task, 'in_progress');

        return [
            'ok' => true,
            'message' => 'Task started'
        ];
    }

    public function completeTask($id, $request)
    {
        $task = $this->dao->getTaskForDriver($id, $request->user()->id);

        $now = now();
        $end = \Carbon\Carbon::parse($task->date . ' ' . $task->end_time);

        if (!in_array($task->status, ['in_progress', 'ready_to_complete'])) {
            return [
                'ok' => false,
                'code' => 400,
                'message' => 'Task not allowed to complete'
            ];
        }

        if ($now->gt($end)) {
            $this->dao->updateTaskStatus($task, 'failed');

            return [
                'ok' => false,
                'code' => 400,
                'message' => 'Task expired and marked as failed'
            ];
        }

        // ✅ تحقق من الزيارات
        if ($this->dao->taskHasUnvisitedStores($task)) {
            return [
                'ok' => false,
                'code' => 400,
                'message' => 'You must visit all stores before completing the task'
            ];
        }

        // ✅ تحقق من المبيعات
        if (!$this->dao->taskHasConfirmedSales($task->id)) {
            return [
                'ok' => false,
                'code' => 400,
                'message' => 'لا يمكن إنهاء المهمة بدون مبيعات'
            ];
        }

        $this->dao->updateTaskStatus($task, 'completed');

        return [
            'ok' => true,
            'message' => 'Task completed'
        ];
    }

    public function myDailyTasks($user)
    {
        $tasks = $this->dao->getMyDailyTasks($user->id);

        return $tasks->map(function ($task) {

            return [
                'id' => $task->id,
                'date' => $task->date,
                'time' => $task->start_time . ' - ' . $task->end_time,
                'status' => $task->status,
                'region' => $task->region->name,

                'stores' => $task->stores->map(function ($store) {
                    return [
                        'id' => $store->id,
                        'name' => $store->name,
                        'lat' => $store->lat,
                        'lng' => $store->lng,
                        'visited' => $store->pivot->visited,
                        'type' => $store->type,
                    ];
                }),
            ];
        });
    }

    public function myStores($user)
    {
        $tasks = $this->dao->getMyStores($user->id);

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

        return [
            'data' => $stores
        ];
    }
}
