<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;

class TaskTimeGuard
{
    public function check($task): void
    {
        $now = now();

        $start = Carbon::parse($task->date . ' ' . $task->start_time);
        $end   = Carbon::parse($task->date . ' ' . $task->end_time);

        // ❌ قبل وقت المهمة
        if ($now->lt($start)) {
            throw new Exception('Task not started yet');
        }

        // ❌ بعد انتهاء المهمة
        if ($now->gt($end)) {
            throw new Exception('Task expired');
        }
    }
}
