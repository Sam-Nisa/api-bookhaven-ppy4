<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification
{
    use Queueable;

    public $messageObj;
    public $sender;

    public function __construct($messageObj, $sender)
    {
        $this->messageObj = $messageObj;
        $this->sender = $sender;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'new_message',
            'message_id' => $this->messageObj->id,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'text' => 'You received a new message from ' . $this->sender->name,
        ];
    }
}
