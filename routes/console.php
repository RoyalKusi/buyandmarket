<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TDD §6.4 rule 2: nightly inventory checksum/repair job.
Schedule::command('inventory:reconcile')->dailyAt('02:00');
