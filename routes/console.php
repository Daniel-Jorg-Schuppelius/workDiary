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

// Downgrade-Karenz: nach Ablauf Daten purgebarer Module entfernen
// (aufbewahrungspflichtige bleiben). Taeglich, idempotent.
Schedule::command('plans:purge')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

// Datenschutz: Fristen-Erinnerungen fuer Betroffenenanfragen (Art. 12). Idempotent.
Schedule::command('privacy:deadlines')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

// Standorterfassung: rohe GPS-Spur nach Aufbewahrungsfrist loeschen
// (Datenminimierung). Besuche/Buchungen bleiben erhalten. Idempotent.
Schedule::command('location:purge-points')
    ->dailyAt('03:45')
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

// Todoist-Aufgabenabgleich (Feature 055, MVP-115): Polling ist die
// verlässliche Quelle (cursor-basiertes Delta), Webhooks nur Beschleuniger.
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

// Benachrichtigungen & Eskalationen (MVP-018): Fristen-Scanner für Offene
// Punkte, Kommunikations-Folgeaktionen und ablaufende Dokumente. Stündlich,
// idempotent (Dedup über notification_dispatch_log).
Schedule::command('notifications:scan-deadlines')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('tickets:scan-sla-breaches')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Lexoffice-Plugin: Pull-Sync der gecachten Kontakte, Artikel und Belege.
// Greift nur, wenn das Plugin aktiv und je Organisation ein API-Key gesetzt
// ist; nicht konfigurierte Organisationen werden in den Commands übersprungen.
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

// Eurostat veröffentlicht die Mindestlöhne halbjährlich (S1/S2). Zwei Läufe
// pro Jahr (15. Jan / 15. Jul) genügen; der Import ist idempotent.
Schedule::command('payroll:import-minimum-wages')
    ->cron('0 4 15 1,7 *')
    ->withoutOverlapping()
    ->onOneServer();

// Geplanter Lieferantenkatalog-Abruf (Feature 050): jede Viertelstunde fällige
// Remote-Quellen ziehen; der Command prüft pro Quelle das Intervall selbst.
Schedule::command('catalog:fetch-due')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
