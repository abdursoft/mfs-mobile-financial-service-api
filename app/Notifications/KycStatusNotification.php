<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class KycStatusNotification extends Notification
{
    use Queueable;

    protected $status;

    public function __construct($status)
    {
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database']; // or ['mail','database'] if you configure email
    }

    public function toArray($notifiable)
    {
        return [
            'message' => "Your KYC request has been {$this->status}.",
        ];
    }
}
