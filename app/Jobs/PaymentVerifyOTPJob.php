<?php

namespace App\Jobs;

use App\Traits\MessageHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PaymentVerifyOTPJob implements ShouldQueue
{
    use Queueable, MessageHandler;

    public $user;
    public $code;
    /**
     * Create a new job instance.
     */
    public function __construct($user, $code)
    {
        $this->user = $user;
        $this->code = $code;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->smsInit("Payment verify OTP {$this->code}. Don't share your PIN and OTP with anyone.", 'Payment OTP', $this->user->phone, null, $this->user->name);
    }
}
