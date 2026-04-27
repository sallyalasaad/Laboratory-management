<?php


namespace App\Services;



use App\Models\DistributionTask;
use Carbon\Carbon;

class TaskTimeGuard
{
    public function validateActiveTask(DistributionTask $task): void
    {
        $now = now();

        $start = Carbon::parse($task->date . ' ' . $task->start_time);
        $end   = Carbon::parse($task->date . ' ' . $task->end_time);

        if ($task->status === 'completed') {
            throw new \Exception("Task already completed");
        }

        if ($task->status === 'failed') {
            throw new \Exception("Task expired");
        }

        if (!$now->between($start, $end)) {
            throw new \Exception("Outside task time window");
        }
    }
}
