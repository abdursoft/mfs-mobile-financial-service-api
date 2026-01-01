<?php

namespace App\Jobs;

use App\Traits\MessageHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PinResetOTPJob implements ShouldQueue
{
    use Queueable, MessageHandler;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $code,
        public $user
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->smsInit("Your PIN reset OTP {$this->code}. Please don't share your OTP and PIN with anyone", 'PIN reset', $this->user->phone, null, $this->user->name);
    }
}
