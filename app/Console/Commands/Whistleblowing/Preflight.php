<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Preflight.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Whistleblowing;

use Illuminate\Console\Command;

/**
 * Produktions-Readiness-Check fuer das Hinweisgebermodul (Phase 6, Haertung).
 * Prueft die sicherheitskritische Konfiguration und meldet OK/WARN/FAIL. Exit 1,
 * sobald ein kritischer Punkt (FAIL) vorliegt – fuer CI/Go-Live-Gate geeignet.
 */
class Preflight extends Command {
    protected $signature = 'whistleblowing:preflight';

    protected $description = 'Prueft die Produktions-Haertung des Hinweisgebermoduls (Go-Live-Gate).';

    private bool $failed = false;

    public function handle(): int {
        $this->line('Hinweisgeber – Produktions-Readiness');
        $this->newLine();

        $this->checkModuleKey();
        $this->checkLookupKey();
        $this->checkDisk();
        $this->checkScanner();
        $this->checkRetention();
        $this->checkSessionSecurity();

        $this->newLine();
        if ($this->failed) {
            $this->error('NICHT bereit: kritische Punkte (FAIL) muessen behoben werden.');

            return self::FAILURE;
        }

        $this->info('Bereit (ggf. Warnungen pruefen).');

        return self::SUCCESS;
    }

    private function checkModuleKey(): void {
        $key = (string) config('whistleblowing.key');
        if ($key === '') {
            $this->markFail('WHISTLEBLOWING_KEY', 'nicht gesetzt – Modul verweigert ohne Schluessel den Dienst.');

            return;
        }
        $decoded = base64_decode($key, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            $this->warn2('WHISTLEBLOWING_KEY', 'keine 32-Byte-base64; wird per SHA-256 normalisiert.');
        }
        // Blast-Radius: NICHT der globale APP_KEY.
        $appKey = (string) config('app.key');
        if ($key === $appKey || 'base64:' . $key === $appKey) {
            $this->markFail('WHISTLEBLOWING_KEY', 'identisch mit APP_KEY – Blast-Radius zu gross. Eigenen Schluessel setzen.');
        } else {
            $this->ok('WHISTLEBLOWING_KEY', 'gesetzt und getrennt vom APP_KEY.');
        }
    }

    private function checkLookupKey(): void {
        if ((string) config('whistleblowing.lookup_key') === '') {
            $this->warn2('WHISTLEBLOWING_LOOKUP_KEY', 'nicht gesetzt – wird aus dem Modul-Key abgeleitet. Eigenen Key empfohlen.');
        } else {
            $this->ok('WHISTLEBLOWING_LOOKUP_KEY', 'gesetzt (getrennt).');
        }
    }

    private function checkDisk(): void {
        $disk = (string) config('whistleblowing.disk');
        if ($disk === '' || config("filesystems.disks.{$disk}") === null) {
            $this->markFail('Disk', "Anhang-Disk '{$disk}' nicht konfiguriert.");
        } else {
            $this->ok('Disk', "privater Disk '{$disk}' konfiguriert.");
        }
    }

    private function checkScanner(): void {
        $scanner = (string) config('whistleblowing.scanner', 'none');
        if ($scanner === 'none') {
            $this->warn2('Scanner', 'kein Malware-Scanner – Anhaenge bleiben in Quarantaene (fail-safe).');
        } else {
            $this->ok('Scanner', "Treiber '{$scanner}' aktiv.");
        }
    }

    private function checkRetention(): void {
        if ((int) config('whistleblowing.retention_months', 0) > 0) {
            $this->ok('Retention', config('whistleblowing.retention_months') . ' Monate.');
        } else {
            $this->markFail('Retention', 'retention_months muss > 0 sein.');
        }
    }

    private function checkSessionSecurity(): void {
        $prod = app()->environment('production');
        $secure = (bool) config('session.secure');
        $httpOnly = (bool) config('session.http_only');
        $sameSite = (string) config('session.same_site');

        if ($prod && ! $secure) {
            $this->markFail('Session', 'session.secure muss in Produktion true sein (Postfach-Cookie).');
        } elseif (! $httpOnly) {
            $this->markFail('Session', 'session.http_only muss true sein.');
        } elseif (! in_array($sameSite, ['lax', 'strict'], true)) {
            $this->warn2('Session', "same_site = '{$sameSite}'; 'strict'/'lax' empfohlen.");
        } else {
            $this->ok('Session', "http_only, same_site={$sameSite}" . ($prod ? ', secure' : '') . '.');
        }
    }

    private function ok(string $label, string $msg): void {
        $this->line("  <fg=green>OK</>   {$label}: {$msg}");
    }

    private function warn2(string $label, string $msg): void {
        $this->line("  <fg=yellow>WARN</> {$label}: {$msg}");
    }

    private function markFail(string $label, string $msg): void {
        $this->failed = true;
        $this->line("  <fg=red>FAIL</> {$label}: {$msg}");
    }
}
