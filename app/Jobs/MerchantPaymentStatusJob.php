<?php

namespace App\Jobs;

use App\Traits\MessageHandler;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MerchantPaymentStatusJob implements ShouldQueue
{
    use Queueable, MessageHandler;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $amount,
        public $chargeAmount,
        public $ref,
        public $transaction,
        public $user,
        public $merchant
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $date = Carbon::parse($this->transaction->created_at, 'UTC') // assume stored as UTC
                ->setTimezone('Asia/Dhaka')
                ->format('Y/m/d h:i:s A');

        $this->smsInit("Payment received Tk{$this->amount} from {$this->user->phone} successful Ref:{$this->ref} on {$date} TxID:{$this->transaction->txn_id} Your new balance is Tk{$this->merchant->wallet->balance}", "Received payment {$this->amount}", $this->merchant->phone, null, $this->merchant->name);
    }
}
