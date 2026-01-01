<?php

namespace App\Jobs;

use App\Traits\MessageHandler;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UserCashinJob implements ShouldQueue
{
    use Queueable, MessageHandler;


    public $user;
    public $agent;
    public $request;
    public $transaction;
    /**
     * Create a new job instance.
     */
    public function __construct($user, $agent, $request, $transaction)
    {
        $this->user = $user;
        $this->agent = $agent;
        $this->request = $request;
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $date = Carbon::parse($this->transaction->created_at, 'UTC') // assume stored as UTC
                ->setTimezone('Asia/Dhaka')
                ->format('Y/m/d h:i:s A');
        $this->smsInit("You have successfully cashed in Tk{$this->request->amount} from {$this->agent->phone} on {$date} TxID:{$this->transaction->txn_id} Your new balance is Tk{$this->user->wallet->balance}", "Cash-in {$this->request->amount}", $this->user->phone, null, $this->user->name);
    }
}
