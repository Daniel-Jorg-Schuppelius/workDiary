<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SystemHealthCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Backup\RestoreTestResult;
use App\Models\{BackupHeartbeat, RestoreTest};
use App\Services\Licensing\{LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Health-Check für „nach dem Update" (Feature 022, MVP): prüft die
 * Grundkonfiguration der Installation und endet mit Exit-Code 0 (gesund)
 * bzw. 1 (mindestens ein Check rot) — geeignet für Update-Skripte, CI und
 * Monitoring. Siehe ../WorkDiary-Architecture/release-prozess.md §3.
 *
 * Bewusst NUR Konfigurations-/Erreichbarkeits-Checks: es wird keine Mail
 * versendet, kein Job dispatcht und nichts verändert (bis auf eine
 * temporäre Schreibprobe im Storage).
 */
class SystemHealthCommand extends Command {
    protected $signature = 'system:health {--json : Ergebnis als JSON ausgeben (für UI/Monitoring) statt als Tabelle}';

    protected $description = 'Prüft die Installation nach einem Update (DB, Migrationen, Storage, Queue, APP_KEY, Mail, Lizenz).';

    public function handle(LicenseService $licenses): int {
        $checks = $this->runChecks($licenses);
        $failed = array_values(array_filter($checks, static fn(array $c): bool => ! $c[1]));

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'version' => (string) config('app.version', '0.1.0-dev'),
                'environment' => (string) app()->environment(),
                'healthy' => $failed === [],
                'checks' => array_map(
                    static fn(array $c): array => ['name' => $c[0], 'ok' => $c[1], 'details' => $c[2]],
                    $checks,
                ),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $failed === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->info(sprintf(
            'WorkDiary Health-Check — Version %s (%s)',
            (string) config('app.version', '0.1.0-dev'),
            (string) app()->environment(),
        ));

        $this->table(
            ['Check', 'Status', 'Details'],
            array_map(static fn(array $c): array => [$c[0], $c[1] ? 'OK' : 'FEHLER', $c[2]], $checks),
        );

        if ($failed !== []) {
            $this->error(sprintf('%d von %d Checks fehlgeschlagen.', count($failed), count($checks)));

            return self::FAILURE;
        }

        $this->info('Alle Checks bestanden.');

        return self::SUCCESS;
    }

    /**
     * Führt alle Checks aus und liefert sie strukturiert — wiederverwendbar
     * für die Tabellen-, JSON- und UI-Darstellung (admin/components).
     *
     * @return list<array{0: string, 1: bool, 2: string}>
     */
    public function runChecks(LicenseService $licenses): array {
        return [
            $this->checkDatabase(),
            $this->checkMigrations(),
            $this->checkStorage(),
            $this->checkQueue(),
            $this->checkAppKey(),
            $this->checkMail(),
            $this->checkLicense($licenses),
            $this->checkBackupFreshness(),
            $this->checkRestoreTest(),
        ];
    }

    /** @return array{0: string, 1: bool, 2: string} */
    private function checkDatabase(): array {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return ['Datenbank', true, sprintf('Verbindung "%s" erreichbar', (string) config('database.default'))];
        } catch (Throwable $e) {
            return ['Datenbank', false, Str::limit($e->getMessage(), 120)];
        }
    }

    /** @return array{0: string, 1: bool, 2: string} */
    private function checkMigrations(): array {
        try {
            // migrate:status --pending liefert FAILURE, sobald Migrationen ausstehen oder die Tabelle fehlt.
            $exit = $this->callSilently('migrate:status', ['--pending' => true]);

            return $exit === self::SUCCESS
                ? ['Migrationen', true, 'Keine ausstehenden Migrationen']
                : ['Migrationen', false, 'Ausstehende Migrationen — php artisan migrate ausführen'];
        } catch (Throwable $e) {
            return ['Migrationen', false, Str::limit($e->getMessage(), 120)];
        }
    }

    /** @return array{0: string, 1: bool, 2: string} */
    private function checkStorage(): array {
        $path = storage_path('app');

        try {
            $probe = $path . DIRECTORY_SEPARATOR . '.health-check-' . Str::random(8);
            if (@file_put_contents($probe, 'ok') === false) {
                return ['Storage', false, sprintf('%s ist nicht beschreibbar', $path)];
            }
            @unlink($probe);

            return ['Storage', true, sprintf('%s beschreibbar', $path)];
        } catch (Throwable $e) {
            return ['Storage', false, Str::limit($e->getMessage(), 120)];
        }
    }

    /** @return array{0: string, 1: bool, 2: string} */
    private function checkQueue(): array {
        $default = (string) config('queue.default', '');
        if ($default === '') {
            return ['Queue', false, 'Keine Queue-Verbindung konfiguriert (QUEUE_CONNECTION)'];
        }

        $connection = config('queue.connections.' . $default);
        if (! is_array($connection)) {
            return ['Queue', false, sprintf('Queue-Verbindung "%s" ist nicht definiert', $default)];
        }

        return ['Queue', true, sprintf('Verbindung "%s" (Driver %s)', $default, (string) ($connection['driver'] ?? '?'))];
    }

    /** @return array{0: string, 1: bool, 2: string} */
    private function checkAppKey(): array {
        $key = (string) config('app.key', '');

        return $key !== ''
            ? ['APP_KEY', true, 'Gesetzt']
            : ['APP_KEY', false, 'Nicht gesetzt — php artisan key:generate ausführen'];
    }

    /** @return array{0: string, 1: bool, 2: string} */
    private function checkMail(): array {
        $default = (string) config('mail.default', '');
        if ($default === '') {
            return ['Mail', false, 'Kein Mail-Transport konfiguriert (MAIL_MAILER)'];
        }

        $mailer = config('mail.mailers.' . $default);
        if (! is_array($mailer)) {
            return ['Mail', false, sprintf('Mailer "%s" ist nicht definiert', $default)];
        }

        $from = (string) config('mail.from.address', '');
        $detail = sprintf('Mailer "%s", From: %s', $default, $from !== '' ? $from : '—');

        // Nur Config-Check (kein Versand); für Produktion weist die Diagnose-Seite gesondert hin.
        return $from !== ''
            ? ['Mail', true, $detail]
            : ['Mail', false, 'Keine Absender-Adresse konfiguriert (MAIL_FROM_ADDRESS)'];
    }

    /**
     * Frische des letzten Backup-Heartbeats (Feature 017). Rot, wenn der
     * jüngste Heartbeat älter als config('backup.heartbeat_freshness_hours')
     * (Default 26 h) ist oder gar keiner vorliegt. Fehlt die Tabelle (frische
     * Installation vor Migration), wird der Check übersprungen (grün), um den
     * Update-Workflow nicht zu blockieren.
     *
     * @return array{0: string, 1: bool, 2: string}
     */
    private function checkBackupFreshness(): array {
        try {
            if (! DB::getSchemaBuilder()->hasTable((new BackupHeartbeat())->getTable())) {
                return ['Backup-Heartbeat', true, 'Tabelle fehlt (vor Migration) — Check übersprungen'];
            }

            /** @var BackupHeartbeat|null $latest */
            $latest = BackupHeartbeat::query()->orderByDesc('occurred_at')->first();
            if ($latest === null) {
                // Noch nie ein Heartbeat (Frischinstallation, Test/CI, Backup nicht eingerichtet) ⇒ Hinweis statt
                // hartem Fehler; der rote „kein Backup"-Hinweis steht auf der Admin-Backup-Statusseite (Feature 017).
                return ['Backup-Heartbeat', true, 'Kein Backup registriert (Hinweis — Backup einrichten)'];
            }

            $maxHours = max(1, (int) config('backup.heartbeat_freshness_hours', 26));
            $ageHours = (int) $latest->occurred_at->diffInHours(CarbonImmutable::now());

            return $ageHours <= $maxHours
                ? ['Backup-Heartbeat', true, sprintf('Letzter Heartbeat vor %d h (Schwelle %d h)', $ageHours, $maxHours)]
                : ['Backup-Heartbeat', false, sprintf('Letzter Heartbeat vor %d h überfällig (Schwelle %d h)', $ageHours, $maxHours)];
        } catch (Throwable $e) {
            return ['Backup-Heartbeat', false, Str::limit($e->getMessage(), 120)];
        }
    }

    /**
     * Überfälligkeit des Restore-Tests (Feature 017, §6.3). Rot, wenn der
     * jüngste ERFOLGREICHE Restore-Test länger als
     * config('backup.restore_test_overdue_days') (Default 180) zurückliegt
     * oder ganz fehlt. Tabelle fehlt ⇒ übersprungen (grün).
     *
     * @return array{0: string, 1: bool, 2: string}
     */
    private function checkRestoreTest(): array {
        try {
            if (! DB::getSchemaBuilder()->hasTable((new RestoreTest())->getTable())) {
                return ['Restore-Test', true, 'Tabelle fehlt (vor Migration) — Check übersprungen'];
            }

            /** @var RestoreTest|null $lastPassed */
            $lastPassed = RestoreTest::query()
                ->where('result', RestoreTestResult::Passed->value)
                ->orderByDesc('tested_on')
                ->first();

            $maxDays = max(1, (int) config('backup.restore_test_overdue_days', 180));
            if ($lastPassed === null) {
                // Noch kein protokollierter Restore-Test ⇒ Hinweis statt hartem Fehler (Überfälligkeit eines
                // bestehenden Tests bleibt rot). Nudge zum Eintragen steht auf der Admin-Statusseite.
                return ['Restore-Test', true, sprintf('Noch kein erfolgreicher Restore-Test (Hinweis — Schwelle %d Tage)', $maxDays)];
            }

            $ageDays = (int) $lastPassed->tested_on->startOfDay()->diffInDays(CarbonImmutable::now()->startOfDay());

            return $ageDays <= $maxDays
                ? ['Restore-Test', true, sprintf('Letzter erfolgreicher Test vor %d Tagen (Schwelle %d)', $ageDays, $maxDays)]
                : ['Restore-Test', false, sprintf('Letzter erfolgreicher Test vor %d Tagen überfällig (Schwelle %d)', $ageDays, $maxDays)];
        } catch (Throwable $e) {
            return ['Restore-Test', false, Str::limit($e->getMessage(), 120)];
        }
    }

    /** @return array{0: string, 1: bool, 2: string} */
    private function checkLicense(LicenseService $licenses): array {
        try {
            if (! $licenses->isEnforced()) {
                return ['Lizenz', true, 'Lizenzprüfung nicht erzwungen (Dev/Test)'];
            }

            $result = $licenses->current();

            // Ohne (gültige) Lizenz läuft die Installation als Free-Tier weiter (hart-Free) — gesund.
            // Rot nur, wenn eine vorhandene Lizenz kaputt ist (manipuliert/abgelaufen/Signatur).
            $broken = in_array($result->status, [
                LicenseStatus::Expired,
                LicenseStatus::Tampered,
                LicenseStatus::BadSignature,
            ], true);

            return ['Lizenz', ! $broken, sprintf('Status: %s', $result->status->value)];
        } catch (Throwable $e) {
            return ['Lizenz', false, Str::limit($e->getMessage(), 120)];
        }
    }
}
