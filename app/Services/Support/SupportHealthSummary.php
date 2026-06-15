<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportHealthSummary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Support;

use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Health-Zusammenfassung für den Supportbericht (Feature 041, MVP).
 *
 * Bewusste Abgrenzung: dieser Helfer DUPLIZIERT die Health-Checks NICHT,
 * sondern ruft den vorhandenen Konsolen-Befehl `system:health --json`
 * (siehe {@see \App\Console\Commands\SystemHealthCommand}) auf und reicht
 * dessen maschinenlesbares Ergebnis weiter. Damit bleibt der DiagnosticsService
 * (Diagnose-Seite, MVP-044) die alleinige Quelle der ausführlichen Sektionen,
 * während der Supportbericht den kompakten DB/Migrationen/Storage/Queue/
 * APP_KEY/Mail/Lizenz/Backup-Statusblock erhält.
 *
 * Die Ausgabe enthält ausschließlich technische Status-Booleans und
 * Hinweistexte aus dem Command — keine Kundendaten, keine Secrets.
 */
class SupportHealthSummary {
    /**
     * @return array{
     *     available: bool,
     *     healthy: bool|null,
     *     version: string|null,
     *     environment: string|null,
     *     checks: list<array{name:string, ok:bool, details:string}>,
     *     failed_count: int,
     *     error?: string,
     * }
     */
    public function collect(): array {
        try {
            // Kein Versand, kein Dispatch: system:health prüft nur Konfiguration
            // und Erreichbarkeit (siehe Command-Doku). --json liefert die
            // Struktur { version, environment, healthy, checks[] }.
            Artisan::call('system:health', ['--json' => true]);
            $output = trim(Artisan::output());

            /** @var array<string, mixed> $decoded */
            $decoded = JsonHelper::decode($output);

            $checks = [];
            foreach ((array) ($decoded['checks'] ?? []) as $check) {
                if (! is_array($check)) {
                    continue;
                }
                $checks[] = [
                    'name' => (string) ($check['name'] ?? ''),
                    'ok' => (bool) ($check['ok'] ?? false),
                    'details' => (string) ($check['details'] ?? ''),
                ];
            }

            $failed = array_values(array_filter($checks, static fn(array $c): bool => ! $c['ok']));

            return [
                'available' => true,
                'healthy' => array_key_exists('healthy', $decoded) ? (bool) $decoded['healthy'] : null,
                'version' => isset($decoded['version']) ? (string) $decoded['version'] : null,
                'environment' => isset($decoded['environment']) ? (string) $decoded['environment'] : null,
                'checks' => $checks,
                'failed_count' => count($failed),
            ];
        } catch (Throwable $e) {
            return [
                'available' => false,
                'healthy' => null,
                'version' => null,
                'environment' => null,
                'checks' => [],
                'failed_count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
