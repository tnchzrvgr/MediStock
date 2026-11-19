<?php

use App\Support\Backup;
use Illuminate\Support\Facades\Schedule;

// every minute we check if a scheduled backup is due (see the Backup & Security page)
// to make it work, keep this running in a second window:  php artisan schedule:work
Schedule::call(function () {
    Backup::runIfDue();
})->everyMinute();
