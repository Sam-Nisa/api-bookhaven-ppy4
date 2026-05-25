<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AuthorRequestSubmittedNotification extends Notification
{
    use Queueable;

    public $authorRequest;
    public $user;

    public function __construct($authorRequest, $user)
    {
        $this->authorRequest = $authorRequest;
        $this->user = $user;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'author_request',
            'request_id' => $this->authorRequest->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'text' => $this->user->name . ' has requested to become an author.',
        ];
    }
}
