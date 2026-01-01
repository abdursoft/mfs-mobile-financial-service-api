<?php

namespace App\Jobs;

use App\Traits\MessageHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PinResetConfirmationJob implements ShouldQueue
{
    use Queueable, MessageHandler;

    /**
     * Create a new job instance.
     */
    public function __construct(
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
        $this->smsInit("Your pin has been successfully reset",'PIN reset confirmation',$this->user->phone,null,$this->user->name);
    }
}
