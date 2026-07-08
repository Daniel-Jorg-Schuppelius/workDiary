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
use Illuminate\Support\Facades\{Artisan, Schedule};

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('archive:run')
    ->dailyAt((string) config('archive.schedule_at', '03:00'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('plans:purge')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('privacy:deadlines')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('location:purge-points')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('integration:purge-inbox')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('chat:send-reminders')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('chat:send-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

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

Schedule::command('plugin:healthcheck --no-fail')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('remote:sync-sessions')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('toggl:import')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('openproject:import')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('todoist:sync')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('openproject:push')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('workdiary:backup:check-restore')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('maintenance:scan-due')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('notifications:scan-deadlines')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('tickets:scan-sla-breaches')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('lexoffice:sync-contacts')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('lexoffice:sync-articles')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('lexoffice:sync-vouchers')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('payroll:import-minimum-wages')
    ->cron('0 4 15 1,7 *')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('catalog:fetch-due')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Sicherheitshinweise (OSV) für installierte Abhängigkeiten (Rang 70).
Schedule::command('security:advisories-pull')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->onOneServer();

// Aufbewahrungs-Review (Restpunkt 66): wöchentlicher Vorschlags-Scan.
Schedule::command('privacy:retention-scan')
    ->weeklyOn(1, '04:30')
    ->withoutOverlapping()
    ->onOneServer();
