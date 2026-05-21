<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : console.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('archive:run')
    ->dailyAt((string) config('archive.schedule_at', '03:00'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('attendance:close-open')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('recurrence:generate')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('events:dispatch-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('events:check-certificates')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('events:materialize-recurrences')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
