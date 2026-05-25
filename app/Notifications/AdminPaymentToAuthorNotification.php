<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminPaymentToAuthorNotification extends Notification
{
    use Queueable;

    public $payout;

    public function __construct($payout)
    {
        $this->payout = $payout;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'payout_completed',
            'payout_id' => $this->payout->id,
            'amount' => $this->payout->amount,
            'text' => 'Admin has successfully sent a payout of $' . number_format($this->payout->amount, 2) . ' to your account.',
        ];
    }
}
