<?php

namespace App\Jobs;

use App\Traits\MessageHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SignupOTPJob implements ShouldQueue
{
    use Queueable, MessageHandler;



    public $otpToken;
    public $request;

    /**
     * Create a new job instance.
     */
    public function __construct($token, $request)
    {
        $this->otpToken = $token;
        $this->request = $request;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $text = config('app.name')." signup OTP: {$this->otpToken} will expire in 3 minutes. Please don't share your OTP and PIN with anyone";
        $this->smsInit($text, 'Sign up OTP', $this->request->phone, null, $this->request->name);
    }
}
