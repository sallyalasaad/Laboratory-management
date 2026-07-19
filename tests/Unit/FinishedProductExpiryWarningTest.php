<?php

namespace Tests\Unit;

use App\Events\FinishedProductExpiryWarning;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class FinishedProductExpiryWarningTest extends TestCase
{
    public function test_event_broadcasts_to_private_channel_with_expected_name_and_payload(): void
    {
        $user = new User();
        $user->id = 42;
        $payload = [
            'batch_id' => 7,
            'product_name' => 'Cheese',
            'batch_number' => 'BATCH-001',
            'expiry_date' => '2026-07-20',
            'quantity' => 100,
            'days_remaining' => 30,
            'message' => 'يوجد منتجات جاهزة ستنتهي صلاحيتها خلال شهر',
        ];

        $event = new FinishedProductExpiryWarning($user, $payload);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-user.42', $channels[0]->name);
        $this->assertSame('finished.product.expiry.warning', $event->broadcastAs());
        $this->assertSame($payload, $event->broadcastWith());
    }
}
