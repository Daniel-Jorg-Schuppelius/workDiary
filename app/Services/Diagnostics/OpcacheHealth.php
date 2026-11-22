<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpcacheHealth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diagnostics;

/**
 * Bewertet den Füllstand des OPcache. Ist er voll (Speicher oder
 * Dateischlüssel), übersetzt PHP alles Weitere bei jeder Anfrage neu — die
 * Anwendung wird seitenweise träge, ohne dass ein Fehler erscheint.
 */
final class OpcacheHealth {
    public const int WARN_PERCENT = 85;

    public const int CRITICAL_PERCENT = 97;

    /**
     * @param  array<string, mixed>|null  $status  Rückgabe von `opcache_get_status(false)`; null = nicht verfügbar
     * @return array{status: DiagnosticStatus, metrics: array<string, scalar|null>, messages: list<string>}
     */
    public static function evaluate(?array $status, string $sapi): array {
        $metrics = ['php.sapi' => $sapi];
        if ($status === null || ($status['opcache_enabled'] ?? false) !== true) {
            $metrics['opcache.enabled'] = false;
            // Auf der Kommandozeile ist der Cache üblicherweise aus; aussagekräftig ist nur der Web-Betrieb.
            if ($sapi === 'cli' || $sapi === 'phpdbg') {
                return ['status' => DiagnosticStatus::Ok, 'metrics' => $metrics, 'messages' => []];
            }

            return ['status' => DiagnosticStatus::Warn, 'metrics' => $metrics, 'messages' => ['OPcache ist aus oder nicht abfragbar — PHP übersetzt den Quelltext bei jeder Anfrage neu.']];
        }

        $memory = (array) ($status['memory_usage'] ?? []);
        $statistics = (array) ($status['opcache_statistics'] ?? []);
        $strings = (array) ($status['interned_strings_usage'] ?? []);

        $used = (int) ($memory['used_memory'] ?? 0);
        $wasted = (int) ($memory['wasted_memory'] ?? 0);
        $total = $used + $wasted + (int) ($memory['free_memory'] ?? 0);
        $memoryPercent = $total > 0 ? (int) round(($used + $wasted) / $total * 100) : 0;

        $keys = (int) ($statistics['num_cached_keys'] ?? 0);
        $maxKeys = (int) ($statistics['max_cached_keys'] ?? 0);
        $keysPercent = $maxKeys > 0 ? (int) round($keys / $maxKeys * 100) : 0;

        $stringsTotal = (int) ($strings['buffer_size'] ?? 0);
        $stringsPercent = $stringsTotal > 0 ? (int) round((int) ($strings['used_memory'] ?? 0) / $stringsTotal * 100) : 0;

        $restarts = (int) ($statistics['oom_restarts'] ?? 0) + (int) ($statistics['hash_restarts'] ?? 0);
        $full = ($status['cache_full'] ?? false) === true;

        $metrics += [
            'opcache.enabled' => true,
            'opcache.memory_mb' => (int) round($total / 1048576),
            'opcache.memory_used_percent' => $memoryPercent,
            'opcache.scripts' => (int) ($statistics['num_cached_scripts'] ?? 0),
            'opcache.keys' => $keys,
            'opcache.keys_max' => $maxKeys,
            'opcache.keys_used_percent' => $keysPercent,
            'opcache.strings_used_percent' => $stringsPercent,
            'opcache.restarts_full' => $restarts,
        ];

        $messages = [];
        $level = DiagnosticStatus::Ok;
        $raise = static function (int $percent, string $what, string $setting) use (&$messages, &$level): void {
            if ($percent >= self::CRITICAL_PERCENT) {
                $level = DiagnosticStatus::Critical;
                $messages[] = sprintf('%s zu %d %% belegt — weitere Dateien übersetzt PHP bei jeder Anfrage neu. %s erhöhen.', $what, $percent, $setting);
            } elseif ($percent >= self::WARN_PERCENT) {
                $level = $level === DiagnosticStatus::Critical ? $level : DiagnosticStatus::Warn;
                $messages[] = sprintf('%s zu %d %% belegt. %s erhöhen.', $what, $percent, $setting);
            }
        };
        $raise($memoryPercent, 'OPcache-Speicher', 'opcache.memory_consumption');
        $raise($keysPercent, 'OPcache-Dateischlüssel', 'opcache.max_accelerated_files');
        if ($stringsPercent >= self::WARN_PERCENT) {
            $level = $level === DiagnosticStatus::Critical ? $level : DiagnosticStatus::Warn;
            $messages[] = sprintf('Puffer für Zeichenketten zu %d %% belegt. opcache.interned_strings_buffer erhöhen.', $stringsPercent);
        }
        if ($full || $restarts > 0) {
            $level = DiagnosticStatus::Critical;
            $messages[] = sprintf('OPcache war voll (%d Neustarts wegen Platzmangel). Grenzen erhöhen und PHP-FPM neu laden.', $restarts);
        }

        return ['status' => $level, 'metrics' => $metrics, 'messages' => $messages];
    }
}
