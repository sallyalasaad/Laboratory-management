<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductionOrder;
use Carbon\Carbon;

class DeleteCompletedOrders extends Command
{
    protected $signature = 'orders:delete-completed';
    protected $description = 'حذف الطلبات المكتملة بعد مرور شهر';

    public function handle()
    {
        $threshold = Carbon::now()->subMonth(); // شهر مضى

        $deleted = ProductionOrder::where('status', 'completed')
            ->where('created_at', '<', $threshold)
            ->delete();

        $this->info("تم حذف $deleted طلب مكتمل.");
    }
}
