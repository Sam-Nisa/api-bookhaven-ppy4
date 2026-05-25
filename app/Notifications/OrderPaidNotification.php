<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderPaidNotification extends Notification
{
    use Queueable;

    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $userName = $this->order->user ? $this->order->user->name : 'Customer';
        return [
            'type' => 'order_paid',
            'order_id' => $this->order->id,
            'text' => 'New order #' . $this->order->id . ' has been paid by ' . $userName . ' for $' . number_format($this->order->total_amount, 2),
        ];
    }
}
