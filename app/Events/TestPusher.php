<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestPusher implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;


    public function __construct($message)
    {
        $this->message = $message;
    }


    public function broadcastOn()
    {
        return new Channel('test-channel');
    }


    public function broadcastAs()
    {
        return 'test.event';
    }
}
