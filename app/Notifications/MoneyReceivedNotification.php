<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MoneyReceivedNotification extends Notification
{
    use Queueable;

    protected $amount;
    protected $from;

    public function __construct($amount, $from)
    {
        $this->amount = $amount;
        $this->from = $from;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => "You received {$this->amount} from {$this->from}.",
        ];
    }
}
