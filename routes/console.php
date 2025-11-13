<?php

use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Optional: keep the default inspiring command
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-cancel expired payment requests every minute
Schedule::command('payments:cancel-expired')
    ->everyMinute()
    ->description('Cancel payment requests that are expired');


// Run on the last day of the month at 23:59
Schedule::command('app:add-revenue')->monthlyOn(Carbon::now()->endOfMonth()->day, '23:59');
