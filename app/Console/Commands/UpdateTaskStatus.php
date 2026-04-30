<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DistributionTask;
use Carbon\Carbon;

class UpdateTaskStatus extends Command
{
    protected $signature = 'tasks:update-status';
    protected $description = 'Update tasks from pending to assigned automatically';

    public function handle()
    {
        $now = now();

        // تشغيل المهام
        DistributionTask::where('status', 'pending')
            ->whereDate('date', $now->toDateString())
            ->get()
            ->each(function ($task) use ($now) {

                $start = Carbon::parse($task->date . ' ' . $task->start_time);
                $end   = Carbon::parse($task->date . ' ' . $task->end_time);

                // 🔥 يبدأ تلقائي فقط عند الوصول لوقت البداية
                if ($now->gte($start) && $now->lte($end)) {
                    $task->update(['status' => 'in_progress']);
                }
                // ❌ فشل المهمة إذا انتهى وقتها وما بدأت
                if ($now->gt($end)) {
                    $task->update(['status' => 'failed']);
                }
            });

        // إنهاء أو فشل المهام
        DistributionTask::where('status', 'in_progress')
            ->get()
            ->each(function ($task) use ($now) {

                $end = Carbon::parse($task->date . ' ' . $task->end_time);

                $total = $task->stores()->count();
                $visited = $task->stores()->wherePivot('visited', true)->count();

                // ✅ إذا خلص كل المحلات
                if ($total > 0 && $visited == $total) {
                    $task->update(['status' => 'completed']);
                    return;
                }

                // ❌ إذا انتهى الوقت وما خلص
                if ($now->gt($end)) {
                    $task->update(['status' => 'failed']);
                }
            });

        $this->info('Tasks updated');
    }
}
