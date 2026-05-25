<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PayoutRequestedNotification extends Notification
{
    use Queueable;

    public $payout;
    public $author;

    public function __construct($payout, $author)
    {
        $this->payout = $payout;
        $this->author = $author;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'payout_request',
            'payout_id' => $this->payout->id,
            'author_id' => $this->author->id,
            'author_name' => $this->author->name,
            'amount' => $this->payout->amount,
            'text' => $this->author->name . ' has requested a payout of $' . number_format($this->payout->amount, 2) . '.',
        ];
    }
}
