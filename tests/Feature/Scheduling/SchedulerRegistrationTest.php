<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerRegistrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Scheduling;

use App\Scheduling\SchedulerRegistrar;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regressionstest MVP-180: Die Registry-Migration darf das Verhalten
 * von Standardinstallationen NICHT ändern. Die Soll-Liste ist der
 * eingefrorene Stand der früheren routes/console.php-Einträge
 * (Kommando → [Cron-Ausdruck, onOneServer, withoutOverlapping]).
 */
class SchedulerRegistrationTest extends TestCase {
    use RefreshDatabase;

    /** @var array<string, array{0: string, 1: bool, 2: bool}> */
    private const EXPECTED = [
        // Neu mit MVP-177 (kein Alt-Eintrag): Selbstüberwachung.
        'scheduler:watchdog' => ['0 * * * *', true, true],
        // Neu mit MVP-058 (kein Alt-Eintrag): Betriebsaufgaben-Sync.
        // Neu mit Feature 072 (MVP-255): Reklamations-Fristeneskalation.
        'claims:escalate' => ['15 7 * * *', true, true],
        'operations:scan' => ['0 * * * *', true, true],
        // Neu mit MVP-054 (kein Alt-Eintrag): Update-Check (Opt-in-Gate im Command).
        'updates:check' => ['30 6 * * *', true, true],
        // Neuigkeiten-Rail (Opt-in-Gate im Command; externer Abruf nie im Web-Request).
        'news-feed:refresh' => ['*/30 * * * *', true, true],
        // Neu mit Bauturbo A17 (MVP-335): täglicher GoBD-Integritätsnachweis.
        'audit:verify' => ['30 2 * * *', true, true],
        // Vollaudit 2026-07 (H10/H13/N17): E-Mail-Eingang, Domain-Sync/-Events,
        // zyklische Inventur — liefen vorher nie automatisch.
        'mail:poll' => ['*/5 * * * *', true, true],
        'domain:sync' => ['40 4 * * *', true, true],
        'domain:events' => ['*/30 * * * *', true, true],
        'inventory:cycle-counts' => ['50 5 * * 1', true, true],
        'inventory:expiring-lots' => ['10 6 * * *', true, true],
        'archive:run' => ['0 3 * * *', true, true],
        'plans:purge' => ['30 3 * * *', true, true],
        'privacy:deadlines' => ['0 6 * * *', true, true],
        'location:purge-points' => ['45 3 * * *', true, true],
        'integration:purge-inbox' => ['0 4 * * *', true, true],
        'chat:send-reminders' => ['* * * * *', false, true],
        'chat:send-scheduled' => ['* * * * *', false, true],
        'attendance:close-open' => ['*/15 * * * *', true, true],
        'recurrence:generate' => ['30 4 * * *', true, true],
        'events:dispatch-reminders' => ['*/5 * * * *', true, true],
        'events:check-certificates' => ['0 6 * * *', true, true],
        'events:materialize-recurrences' => ['0 2 * * *', true, true],
        'plugin:healthcheck --no-fail' => ['0 * * * *', true, true],
        'remote:sync-sessions' => ['0 * * * *', true, true],
        'toggl:import' => ['0 * * * *', true, true],
        'openproject:import' => ['0 * * * *', true, true],
        'todoist:sync' => ['0 * * * *', true, true],
        // Neu mit Feature 095: Calendly-Termin-Backfill (Polling/Reconciliation).
        'calendly:backfill' => ['0 * * * *', true, true],
        // Neu mit Feature 080 (MVP-359): Cloud-Dokumenteingang-Delta-Lauf.
        'cloud-intake:sync' => ['*/15 * * * *', true, true],
        // Neu mit Feature 017 Phase 32 (MVP-364/365): Cloud-Backup + Verify.
        'workdiary:backup:run' => ['30 1 * * *', true, true],
        'workdiary:backup:verify' => ['30 3 * * 6', true, true],
        // Neu mit Bauturbo A9 (MVP-329): CardDAV-Kontakt-Lese-Sync.
        'carddav:sync' => ['0 * * * *', true, true],
        'openproject:push' => ['0 * * * *', true, true],
        'workdiary:backup:check-restore' => ['0 5 * * *', true, true],
        'maintenance:scan-due' => ['30 5 * * *', true, true],
        'notifications:scan-deadlines' => ['0 * * * *', true, true],
        'tickets:scan-sla-breaches' => ['*/5 * * * *', true, true],
        'lexoffice:sync-contacts' => ['0 * * * *', true, true],
        'lexoffice:sync-articles' => ['0 * * * *', true, true],
        'lexoffice:sync-vouchers' => ['0 * * * *', true, true],
        // Neu mit Feature 078 (MVP-322): JTL-Wawi-Projektions-Sync.
        'jtl:sync' => ['0 * * * *', true, true],
        // Neu mit Feature 093 (MVP-433/434): Billbee-Multichannel-Sync.
        'billbee:sync' => ['*/15 * * * *', true, true],
        // Neu mit Feature 093 (MVP-431): easybill-Beleg-Rückabruf.
        'easybill:sync' => ['0 * * * *', true, true],
        // Neu mit Feature 077 (MVP-313): orgaMAX-Projektions-Sync.
        'orgamax:sync' => ['0 * * * *', true, true],
        'payroll:import-minimum-wages' => ['0 4 15 1,7 *', true, true],
        'catalog:fetch-due' => ['*/15 * * * *', true, true],
        'security:advisories-pull' => ['30 5 * * *', true, true],
        'privacy:retention-scan' => ['30 4 * * 1', true, true],
        // Neu mit Feature 006 (Welle D): ArbZG-Verstoß-Persistenz.
        'compliance:scan-findings' => ['30 1 * * *', true, true],
        // Neu mit Phase 36 (MVP-411): KI-Betriebslauf.
        'ai:maintenance' => ['40 5 * * *', true, true],
        // Neu mit Phase 38 (MVP-415): wiederkehrende Rechnungsentwürfe.
        'invoices:generate-recurring' => ['15 5 * * *', true, true],
        // Neu mit Feature 095 (MVP-441): tägliche Quelltext-Integritätsprüfung.
        'integrity:verify --trigger=schedule' => ['20 3 * * *', true, true],
        // Neu mit Feature 096 (MVP-445): Angriffserkennungs-Auswertung.
        'security:evaluate' => ['*/5 * * * *', true, true],
        // Neu mit Feature 098: Kunden-Sonderkonditionen — Monatsrechnungen +
        // Retainer-Pauschalen an Lexoffice (jeweils am Monatsersten).
        'customer-billing:generate-invoices' => ['25 5 1 * *', true, true],
        'customer-billing:push-retainers' => ['35 5 1 * *', true, true],
    ];

    /** @return array<string, array{expression: string, onOneServer: bool, withoutOverlapping: bool}> */
    private function registeredCommands(): array {
        $schedule = new Schedule;
        app(SchedulerRegistrar::class)->register($schedule);

        $commands = [];
        foreach ($schedule->events() as $event) {
            if (!is_string($event->command) || $event->command === '') {
                continue;
            }
            $name = trim(Str::after($event->command, "'artisan' "));
            $commands[$name] = [
                'expression' => $event->expression,
                'onOneServer' => (bool) $event->onOneServer,
                'withoutOverlapping' => (bool) $event->withoutOverlapping,
            ];
        }

        return $commands;
    }

    public function test_all_legacy_schedule_entries_are_preserved_exactly(): void {
        $commands = $this->registeredCommands();

        $this->assertCount(count(self::EXPECTED), $commands, 'Anzahl der Registry-Jobs weicht vom eingefrorenen Stand ab: ' . implode(', ', array_keys($commands)));

        foreach (self::EXPECTED as $command => [$expression, $onOneServer, $withoutOverlapping]) {
            $this->assertArrayHasKey($command, $commands, "Job [{$command}] fehlt in der Registry.");
            $this->assertSame($expression, $commands[$command]['expression'], "Cron-Ausdruck von [{$command}] geändert.");
            $this->assertSame($onOneServer, $commands[$command]['onOneServer'], "onOneServer von [{$command}] geändert.");
            $this->assertSame($withoutOverlapping, $commands[$command]['withoutOverlapping'], "withoutOverlapping von [{$command}] geändert.");
        }
    }

    public function test_archive_schedule_time_respects_env_config(): void {
        config(['archive.schedule_at' => '02:15']);

        $commands = $this->registeredCommands();

        $this->assertSame('15 2 * * *', $commands['archive:run']['expression']);
    }

    public function test_archive_schedule_time_respects_system_setting_override(): void {
        Setting::set('archive.schedule_at', '04:45', SettingScope::System);

        $commands = $this->registeredCommands();

        $this->assertSame('45 4 * * *', $commands['archive:run']['expression']);
    }

    public function test_invalid_setting_time_falls_back_to_default(): void {
        config(['archive.schedule_at' => 'kaputt']);

        $commands = $this->registeredCommands();

        $this->assertSame('0 3 * * *', $commands['archive:run']['expression']);
    }

    public function test_scheduler_heartbeat_writer_is_registered(): void {
        $schedule = new Schedule;
        app(SchedulerRegistrar::class)->register($schedule);

        $heartbeat = collect($schedule->events())
            ->first(fn($event): bool => $event->description === SchedulerRegistrar::HEARTBEAT_NAME);

        $this->assertNotNull($heartbeat, 'Scheduler-Heartbeat-Writer fehlt.');
        $this->assertSame('* * * * *', $heartbeat->expression);
    }
}
