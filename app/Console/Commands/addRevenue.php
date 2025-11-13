<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserRevenue;
use Carbon\Carbon;
use Illuminate\Console\Command;

class addRevenue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-revenue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();

        $agents = User::where('role', 'agent')->get();

        foreach ($agents as $agent) {
            $revenue = $agent->transactionTo()
                ->whereMonth('created_at', $today->month)
                ->whereYear('created_at', $today->year)
                ->sum('interest');

            $agent->wallet->balance += $revenue;
            $agent->wallet->save();

            UserRevenue::create([
                'amount' => $revenue,
                'user_id' => $agent->id,
                'note' => "Revenue Added ".$revenue
            ]);
        }
    }
}
