<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AuthorRequestStatusNotification extends Notification
{
    use Queueable;

    public $authorRequest;
    public $status;

    public function __construct($authorRequest, $status)
    {
        $this->authorRequest = $authorRequest;
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'author_request_status',
            'request_id' => $this->authorRequest->id,
            'status' => $this->status,
            'text' => 'Your request to become an author has been ' . $this->status . '.',
        ];
    }
}
