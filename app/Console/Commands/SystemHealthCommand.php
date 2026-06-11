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

use App\Services\Licensing\{LicenseService, LicenseStatus};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Health-Check für „nach dem Update" (Feature 022, MVP): prüft die
 * Grundkonfiguration der Installation und endet mit Exit-Code 0 (gesund)
 * bzw. 1 (mindestens ein Check rot) — geeignet für Update-Skripte, CI und
 * Monitoring. Siehe docs/release-prozess.md §3.
 *
 * Bewusst NUR Konfigurations-/Erreichbarkeits-Checks: es wird keine Mail
 * versendet, kein Job dispatcht und nichts verändert (bis auf eine
 * temporäre Schreibprobe im Storage).
 */
class SystemHealthCommand extends Command {
    protected $signature = 'system:health';

    protected $description = 'Prüft die Installation nach einem Update (DB, Migrationen, Storage, Queue, APP_KEY, Mail, Lizenz).';

    public function handle(LicenseService $licenses): int {
        $this->info(sprintf(
            'WorkDiary Health-Check — Version %s (%s)',
            (string) config('app.version', '0.1.0-dev'),
            (string) app()->environment(),
        ));

        /** @var list<array{0: string, 1: bool, 2: string}> $checks */
        $checks = [
            $this->checkDatabase(),
            $this->checkMigrations(),
            $this->checkStorage(),
            $this->checkQueue(),
            $this->checkAppKey(),
            $this->checkMail(),
            $this->checkLicense($licenses),
        ];

        $this->table(
            ['Check', 'Status', 'Details'],
            array_map(static fn(array $c): array => [$c[0], $c[1] ? 'OK' : 'FEHLER', $c[2]], $checks),
        );

        $failed = array_values(array_filter($checks, static fn(array $c): bool => ! $c[1]));
        if ($failed !== []) {
            $this->error(sprintf('%d von %d Checks fehlgeschlagen.', count($failed), count($checks)));

            return self::FAILURE;
        }

        $this->info('Alle Checks bestanden.');

        return self::SUCCESS;
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
            // migrate:status --pending liefert FAILURE, sobald Migrationen
            // ausstehen oder die migrations-Tabelle fehlt.
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

        // Nur Config-Check (kein Versand): log/array sind gültig konfiguriert,
        // für Produktion weist die Diagnose-Seite gesondert darauf hin.
        return $from !== ''
            ? ['Mail', true, $detail]
            : ['Mail', false, 'Keine Absender-Adresse konfiguriert (MAIL_FROM_ADDRESS)'];
    }

    /** @return array{0: string, 1: bool, 2: string} */
    private function checkLicense(LicenseService $licenses): array {
        try {
            if (! $licenses->isEnforced()) {
                return ['Lizenz', true, 'Lizenzprüfung nicht erzwungen (Dev/Test)'];
            }

            $result = $licenses->current();

            // Ohne (gültige) Lizenz läuft die Installation als Free-Tier weiter
            // (hart-Free) — gesund. Rot wird der Check nur, wenn eine
            // vorhandene Lizenz kaputt ist (manipuliert/abgelaufen/Signatur).
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
