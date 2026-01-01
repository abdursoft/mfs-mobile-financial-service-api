<?php

namespace App\Jobs;

use App\Traits\MessageHandler;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MerchantPaymentReceivedJob implements ShouldQueue
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

        $this->smsInit("You have received a payment Tk{$this->transaction->amount} from {$this->user->phone} on {$date} TxnID:{$this->transaction->txn_id}. Your new balance is Tk{$this->merchant->wallet->balance}", "Received Payment Tk{$this->transaction->amount}", $this->merchant->phone, null, $this->merchant->name);
    }
}
