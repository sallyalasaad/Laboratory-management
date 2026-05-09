<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DistributionTask;
use Carbon\Carbon;

class UpdateTaskStatus extends Command
{
    protected $signature = 'tasks:update-status';

    protected $description = 'Handle automatic task start and finish';

    public function handle()
    {
        $now = now();

        DistributionTask::with('stores')
            ->whereIn('status', ['pending', 'in_progress'])
            ->get()
            ->each(function ($task) use ($now) {

                $start = Carbon::parse(
                    $task->date . ' ' . $task->start_time
                );

                $end = Carbon::parse(
                    $task->date . ' ' . $task->end_time
                );

                /*
                |--------------------------------------------------------------------------
                | AUTO START
                |--------------------------------------------------------------------------
                | إذا دخلنا وقت البداية والمهمة ما بدأت
                */

                if (
                    $task->status === 'pending' &&
                    $now->gte($start) &&
                    $now->lt($end)
                ) {

                    $task->update([
                        'status' => 'in_progress'
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | AUTO FINISH
                |--------------------------------------------------------------------------
                | إذا انتهى الوقت:
                | - كل المحلات مزارة => completed
                | - يوجد محلات غير مزارة => failed
                */

                if ($now->gte($end)) {

                    $hasUnvisitedStores = $task->stores()
                        ->wherePivot('visited', false)
                        ->exists();

                    if ($hasUnvisitedStores) {

                        $task->update([
                            'status' => 'failed'
                        ]);

                    } else {

                        $task->update([
                            'status' => 'completed'
                        ]);
                    }
                }
            });

        $this->info('Tasks updated successfully');
    }
}
