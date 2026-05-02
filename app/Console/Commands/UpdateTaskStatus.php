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

        DistributionTask::where('status','in_progress')
            ->get()
            ->each(function($task) use ($now) {

                $end = \Carbon\Carbon::parse($task->date.' '.$task->end_time);

                if ($now->gt($end)) {
                    $task->update(['status'=>'completed']);
                }
            });

        $this->info('done');
    }
}
