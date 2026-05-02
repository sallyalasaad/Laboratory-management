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

        // 🔥 بداية السماح = قبل ساعة من البداية
        $allowedStart = $start->copy()->subHour();

        // ❌ قبل وقت السماح (مش قبل start)
        if ($now->lt($allowedStart)) {
            throw new Exception('Task not started yet');
        }

        // ❌ بعد انتهاء المهمة
        if ($now->gt($end)) {
            throw new Exception('Task expired');
        }
    }




}
