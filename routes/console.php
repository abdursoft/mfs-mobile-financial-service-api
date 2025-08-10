<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Optional: keep the default inspiring command
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ✅ Auto-cancel expired payment requests every minute
Schedule::command('payments:cancel-expired')
    ->everyMinute()
    ->description('Cancel payment requests that are expired');
