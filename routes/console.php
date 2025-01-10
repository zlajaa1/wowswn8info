<?php

use App\Http\Controllers\PlayerShipController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ClanController;
use App\Http\Controllers\ClanMemberController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ShipController;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Add a test task that runs every minute
//Schedule::call(function () {
//  Log::info('Test scheduler task running at: ' . now());
//})->everyMinute();


Schedule::command('fetch-store:ships')
    ->monthlyOn(15, '01:00')
    ->before(function () {
        Log::info('Fetch ships cron started');
    })
    ->after(function () {
        Log::info('Fetch ships cron completed');
    })
    ->onFailure(function ($exception) {
        Log::error('Fetch ships cron failed ' . $exception->getMessage());
    });


Schedule::command('fetch-store:player-ships')
    ->dailyAt('02:00')
    ->before(function () {
        Log::info('Player ships fetching cron started');
    })
    ->after(function () {
        Log::info('Player ships fetching cron completed');
    })
    ->onFailure(function ($exception) {
        Log::error('Player ships fetching cron failed: ' . $exception->getMessage());
    });

Schedule::command('fetch-store:players-data')
    ->weeklyOn(3, '03:00')
    ->before(function () {
        Log::info('Player data fetching cron (EU server) started');
    })
    ->after(function () {
        Log::info('Player data fetching cron (EU server) successfully completed');
    })
    ->onFailure(function ($exception) {
        Log::error('Player data fetching cron (EU server) failed: ' . $exception->getMessage());
    });

Schedule::command('fetch-store:clans')
    ->weeklyOn(4, '02:00')
    ->before(function () {
        Log::info('Clan data fetching cron started');
    })
    ->after(function () {
        Log::info('Clan data fetching cron completed');
    })
    ->onFailure(function ($exception) {
        Log::error('Clan data fetching cron failed: ' . $exception->getMessage());
    });

Schedule::command('fetch-store:clan-members')
    ->weeklyOn(4, '04:30')
    ->before(function () {
        Log::info('Clan member data fetching cron started');
    })
    ->after(function () {
        Log::info('Clan member data fetching cron completed');
    })
    ->onFailure(function ($exception) {
        Log::error('Clan member data fetching cron failed: ' . $exception->getMessage());
    });
