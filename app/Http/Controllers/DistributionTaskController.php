<?php

namespace App\Http\Controllers;
use App\Models\Region;
use App\Models\User;
use App\Models\Store;
use App\Models\DistributionTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\DistributionTaskService;

class DistributionTaskController extends Controller
{
    protected $service;

    public function __construct(DistributionTaskService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        return $this->service->createTask($request);
    }


     // جلب السائقين

    public function drivers(\App\Services\DistributionTaskService $service)
    {
        return response()->json(
            $service->getDrivers()
        );
    }


     // عرض مهمة واحدة بالتفاصيل

    public function show($id, \App\Services\DistributionTaskService $service)
    {
        return response()->json(
            $service->show($id)
        );
    }


     //عرض كل المهام

    public function index(\App\Services\DistributionTaskService $service)
    {
        return response()->json(
            $service->index()
        );
    }


    // تعديل المهمة

    public function update(Request $request, $id, \App\Services\DistributionTaskService $service)
    {
        $result = $service->update($request, $id);

        if (!$result['ok']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['code']);
        }

        return response()->json([
            'message' => 'Task updated',
            'task' => $result['task']
        ]);
    }

     // مهام سائق معين

    public function driverTasks($driverId, \App\Services\DistributionTaskService $service)
    {
        return response()->json(
            $service->driverTasks($driverId)
        );
    }



    // عرض المهام اليومية

    public function todayTasks(\App\Services\DistributionTaskService $service)
    {
        return response()->json(
            $service->todayTasks()
        );
    }


      //عرض المهام اليومية لسائق معين

    public function driverTodayTasks($driverId, \App\Services\DistributionTaskService $service)
    {
        return response()->json(
            $service->driverTodayTasks($driverId)
        );
    }

    /////////////////////////////////////////////////////////
    ///عرض المهمة الحالية للسائق
    public function myTodayTask(\App\Services\DistributionTaskService $service)
    {
        return response()->json(
            $service->myTodayTask(auth()->user())
        );
    }

////بدء المهمة
    public function startTask($id, Request $request, \App\Services\DistributionTaskService $service)
    {
        $result = $service->startTask($id, $request);

        if (!$result['ok']) {
            return response()->json([
                'message' => $result['message']
            ], $result['code']);
        }

        return response()->json([
            'message' => $result['message']
        ]);
    }

///إنهاء المهمة
    public function completeTask($id, Request $request, \App\Services\DistributionTaskService $service)
    {
        $result = $service->completeTask($id, $request);

        if (!$result['ok']) {
            return response()->json([
                'message' => $result['message']
            ], $result['code']);
        }

        return response()->json([
            'message' => $result['message']
        ]);
    }

///عرض المهام اليومية من قبل  السائق
    public function myDailyTasks(\App\Services\DistributionTaskService $service)
{
    return response()->json(
        $service->myDailyTasks(auth()->user())
    );
}
//عرض المحلات للسائق

    public function myStores(\App\Services\DistributionTaskService $service)
    {
        return response()->json(
            $service->myStores(auth()->user())
        );
    }
}
