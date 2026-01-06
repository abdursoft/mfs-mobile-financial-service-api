<?php

namespace App\Jobs;

use App\Traits\MessageHandler;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UserPaymentCompleteJob implements ShouldQueue
{
    use Queueable, MessageHandler;

    public $user;
    public $merchant;
    public $transaction;

    /**
     * Create a new job instance.
     */
    public function __construct($user, $merchant, $transaction)
    {
        $this->user = $user;
        $this->merchant = $merchant;
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

        $this->smsInit("Your payment has been completed to {$this->merchant->phone} Tk{$this->transaction->amount} fee:{$this->transaction->charge_amount} on {$date} TxnID:{$this->transaction->txn_id}. Your new balance is Tk{$this->user->wallet->balance}", "You have paid Tk{$this->transaction->amount}", $this->user->phone, null, $this->user->name);
    }
}

