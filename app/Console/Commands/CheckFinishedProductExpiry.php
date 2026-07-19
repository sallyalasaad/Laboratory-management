<?php

namespace App\Console\Commands;

use App\Events\FinishedProductExpiryWarning;
use App\Models\FinishedProductBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class CheckFinishedProductExpiry extends Command
{
    protected $signature = 'finished-products:check-expiry';

    protected $description = 'Check finished product batches nearing expiry and broadcast warnings to product storekeepers';

    public function handle()
    {
        $this->ensureNotificationColumns();

        $today = Carbon::today();
        $cutoffDate = $today->copy()->addDays(30)->toDateString();

        $batches = FinishedProductBatch::with('finishedProduct')
            ->where('expiry_date', '>', $today->toDateString())
            ->where('expiry_date', '<=', $cutoffDate)
            ->where('remaining_quantity', '>', 0)
            ->get();

        $eligibleBatches = $batches->filter(function ($batch) {
            $status = strtolower((string) ($batch->status ?? ''));

            return $status === '' || !in_array($status, ['inactive', 'damaged', 'destroyed', 'wasted', 'waste', 'discarded', 'archived'], true);
        });

        $warehouseManager = User::role('product_storekeeper')->first();

        if (!$warehouseManager) {
            $this->warn('No product_storekeeper user found.');
            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($eligibleBatches as $batch) {
            if ($this->hasNotificationForBatch($batch->id)) {
                continue;
            }

          $daysRemaining = $today->diffInDays(
    Carbon::parse($batch->expiry_date)
);

            $payload = [
                'batch_id' => $batch->id,
                'product_name' => $batch->finishedProduct->name ?? '',
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date,
                'quantity' => (float) $batch->remaining_quantity,
                'days_remaining' => $daysRemaining,
                'message' => 'يوجد منتجات جاهزة ستنتهي صلاحيتها خلال شهر',
            ];

            event(new FinishedProductExpiryWarning($warehouseManager, $payload));

            $this->storeSentNotification($batch->id, $warehouseManager->id, $payload['message']);
            $sentCount++;
        }

        $this->info("Finished product expiry warnings sent: {$sentCount}");

        return self::SUCCESS;
    }

    private function ensureNotificationColumns(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        if (!Schema::hasColumn('notifications', 'user_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            });
        }

        if (!Schema::hasColumn('notifications', 'message')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->text('message')->nullable()->after('user_id');
            });
        }

        if (!Schema::hasColumn('notifications', 'status')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('status')->default('pending')->after('message');
            });
        }

        if (!Schema::hasColumn('notifications', 'batch_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('batch_id')->nullable()->after('status');
            });
        }
    }

    private function hasNotificationForBatch(int $batchId): bool
    {
        return DB::table('notifications')
            ->where('batch_id', $batchId)
            ->where('status', 'sent')
            ->exists();
    }

    private function storeSentNotification(int $batchId, int $userId, string $message): void
    {
        DB::table('notifications')->insert([
            'batch_id' => $batchId,
            'user_id' => $userId,
            'message' => $message,
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
