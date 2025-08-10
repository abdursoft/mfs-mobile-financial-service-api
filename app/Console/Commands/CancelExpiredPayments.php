<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PaymentRequest;
use Carbon\Carbon;

class CancelExpiredPayments extends Command
{
    protected $signature = 'payments:cancel-expired';
    protected $description = 'Cancel payment requests that are expired';

    public function handle()
    {
        $now = Carbon::now();

        $count = PaymentRequest::where('status', 'pending')
            ->where('expires_at', '<', $now)
            ->update(['status' => 'cancelled']);

        $this->info("Cancelled {$count} expired payment requests.");
    }
}
