<?php
namespace App\DAO;

use App\Models\DistributionTask;
use App\Models\Store;
use App\Models\User;

class DistributionTaskDAO
{
    public function checkOverlap($userId, $date, $newStart, $newEnd)
    {
        return DistributionTask::where('user_id', $userId)
            ->whereDate('date', $date)
            ->get()
            ->first(function ($task) use ($newStart, $newEnd) {

                $taskStart = \Carbon\Carbon::parse($task->date.' '.$task->start_time)->subHour();
                $taskEnd   = \Carbon\Carbon::parse($task->date.' '.$task->end_time);

                return $newStart < $taskEnd && $newEnd > $taskStart;
            });
    }

    public function createTask($driverId, $data)
    {
        return DistributionTask::create([
            'user_id'    => $driverId,
            'region_id'  => $data['region_id'],
            'date'       => $data['date'],
            'start_time' => $data['start_time'],
            'end_time'   => $data['end_time'],
            'status'     => 'pending',
        ]);
    }

    public function getRegionStores($regionId)
    {
        return Store::where('region_id', $regionId)->get();
    }

    public function attachStores($task, $attachData)
    {
        $task->stores()->attach($attachData);
    }

    public function getDrivers()
    {
        return User::role('driver')->get(['id', 'name']);
    }

    public function getTaskById($id)
    {
        return DistributionTask::with(['user', 'region', 'stores'])
            ->findOrFail($id);
    }
    public function getAllTasks()
    {
        return DistributionTask::with(['user', 'region', 'stores'])
            ->latest()
            ->get();
    }
    public function getTaskWithStores($id)
    {
        return DistributionTask::with('stores')->findOrFail($id);
    }

    public function getDriverById($id)
    {
        return User::role('driver')
            ->where('id', $id)
            ->first();
    }

    public function updateTask($task, $data)
    {
        $task->update($data);
        return $task;
    }

    public function syncStores($task, $attachData)
    {
        $task->stores()->sync($attachData);
    }

    public function checkOverlapUpdate($userId, $taskId, $date, $startT, $endT)
    {
        return DistributionTask::where('user_id', $userId)
            ->where('id', '!=', $taskId)
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
    }

    public function getDriverTasks($driverId)
    {
        return DistributionTask::with('stores')
            ->where('user_id', $driverId)
            ->get();
    }

    public function getTodayTasks()
    {
        return DistributionTask::with(['user', 'region', 'stores'])
            ->whereDate('date', now()->toDateString())
            ->get();
    }

    public function getDriverTodayTasks($driverId)
    {
        return DistributionTask::with(['region', 'stores'])
            ->where('user_id', $driverId)
            ->whereDate('date', now()->toDateString())
            ->get();
    }
    public function getDriverTodayTasksWithRelations($driverId)
    {
        return DistributionTask::with(['region', 'stores'])
            ->where('user_id', $driverId)
            ->whereDate('date', now()->toDateString())
            ->orderBy('start_time')
            ->get();
    }

    public function getTaskForDriver($taskId, $userId)
    {
        return DistributionTask::where('id', $taskId)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    public function updateTaskStatus($task, $status)
    {
        return $task->update(['status' => $status]);
    }



    public function taskHasUnvisitedStores($task)
    {
        return $task->stores()
            ->wherePivot('visited', false)
            ->exists();
    }

    public function taskHasConfirmedSales($taskId)
    {
        return \App\Models\Sale::where('distribution_task_id', $taskId)
            ->where('status', 'confirmed')
            ->exists();
    }


    public function getMyDailyTasks($userId)
    {
        return DistributionTask::with(['region', 'stores'])
            ->where('user_id', $userId)
            ->whereDate('date', now()->toDateString())
            ->orderBy('start_time')
            ->get();
    }


    public function getMyStores($userId)
    {
        return DistributionTask::with(['stores'])
            ->where('user_id', $userId)
            ->whereDate('date', now()->toDateString())
            ->get();
    }



}
