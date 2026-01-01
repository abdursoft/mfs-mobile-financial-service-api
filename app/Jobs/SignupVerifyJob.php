<?php

namespace App\Jobs;

use App\Traits\MessageHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SignupVerifyJob implements ShouldQueue
{
    use Queueable, MessageHandler;

    public $user;
    /**
     * Create a new job instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->smsInit("{$this->user->name} your account has been verified, Please set your wallet PIN & update your KYC", 'Account Verified', $this->user->phone, $this->user->email, $this->user->name);
    }
}
