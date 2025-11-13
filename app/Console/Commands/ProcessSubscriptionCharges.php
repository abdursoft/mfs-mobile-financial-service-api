<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Carbon\Carbon;

class ProcessSubscriptionCharges extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:charge';

    /**
     * The console command description.
     */
    protected $description = 'Charge active subscriptions after completing their billing cycle';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // Fetch only active subscriptions whose next_charge_date has passed
        $subscriptions = Subscription::with(['product.price'])
            ->where('status', 'active')
            ->whereNotNull('next_charge_date')
            ->where('next_charge_date', '<=', $now)
            ->get();

        $chargedCount = 0;

        foreach ($subscriptions as $subscription) {
            $price = $subscription->product->price;
            $cycle = $price->cycle ?? 'monthly';

            // Log info
            $this->info("Processing subscription: {$subscription->sub_id} (Cycle: {$cycle})");

            // Simulate payment
            // TODO: Integrate with real payment gateway here
            $paymentSuccess = true;

            if ($paymentSuccess) {
                // Calculate next charge date based on cycle
                $nextCharge = match ($cycle) {
                    'daily'     => $now->copy()->addDay(),
                    'weekly'    => $now->copy()->addWeek(),
                    'monthly'   => $now->copy()->addMonth(),
                    'quarterly' => $now->copy()->addMonths(3),
                    'yearly'    => $now->copy()->addYear(),
                    default     => null,
                };

                $subscription->update([
                    'last_charged_at' => $now,
                    'next_charge_date' => $nextCharge,
                ]);

                $chargedCount++;
                $this->line("✅ Charged: {$subscription->price} | Next charge on {$nextCharge}");
            } else {
                $this->error("❌ Charge failed for subscription {$subscription->sub_id}");
                // Optional: $subscription->update(['status' => 'suspended']);
            }
        }

        $this->info("🎯 Total charged subscriptions: {$chargedCount}");
        return Command::SUCCESS;
    }
}
