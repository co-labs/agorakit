<?php

namespace App\Events;

use App\Discussion;
use App\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UserJoinDiscussion implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var User
     */
    private $user;
    /**
     * @var Discussion
     */
    private $discussion;

    /**
     * Create a new event instance.
     *
     * @param User $user
     * @param Discussion $discussion
     */
    public function __construct($user = null, $discussion = null)
    {
        $this->user = $user;
        $this->discussion = $discussion;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('discussions');
    }
}
