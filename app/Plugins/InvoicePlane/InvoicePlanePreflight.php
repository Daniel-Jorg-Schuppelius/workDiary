<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePlanePreflight.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane;

use App\Plugins\InvoicePlane\Schema\{PreflightResult, SchemaReader};
use CommonToolkit\Helper\Data\CryptoHelper;

/**
 * Schema-Preflight/Healthcheck des InvoicePlane-Plugins (Feature 086, MVP-419).
 *
 * Liest Version, Präfix, Zeichensatz, Serverzeit und einen Schema-Fingerprint
 * und entscheidet gegen die Versions-/Capability-Matrix
 * ({@see config/invoiceplane.php}), ob der Adapter freigegeben ist. Unbekannte
 * oder blockierte Versionen sowie fehlende Pflichtspalten führen zu einem
 * **Blocked-State** — nie zu einem stillen Best-Effort-Schreibversuch.
 */
class InvoicePlanePreflight {
    /**
     * @param  array<string, array<string, mixed>>  $versions
     */
    public function __construct(
        private readonly array $versions,
        private readonly int $maxClockDriftSeconds,
    ) {}

    public static function fromConfig(): self {
        /** @var array<string, array<string, mixed>> $versions */
        $versions = (array) config('invoiceplane.versions', []);

        return new self($versions, (int) config('invoiceplane.max_clock_drift_seconds', 300));
    }

    public function run(SchemaReader $reader, ?int $nowUnixForDrift = null): PreflightResult {
        $reasons = [];

        $rawVersion = $reader->version();
        $versionKey = $this->resolveVersionKey($rawVersion);

        if ($versionKey === null) {
            $reasons[] = 'Unbekannte oder nicht unterstützte InvoicePlane-Version: ' . ($rawVersion ?? 'n/a');
        } else {
            $spec = $this->versions[$versionKey];
            if (($spec['status'] ?? null) !== 'supported') {
                $reasons[] = 'Version blockiert (' . $versionKey . '): ' . (string) ($spec['reason'] ?? 'nicht freigegeben');
            } else {
                $this->checkRequiredColumns($reader, $spec, $reasons);
            }
        }

        $this->checkClockDrift($reader, $nowUnixForDrift, $reasons);

        return new PreflightResult(
            ok: $reasons === [],
            versionKey: $versionKey,
            reasons: $reasons,
            fingerprint: $this->fingerprint($reader, $versionKey),
        );
    }

    private function resolveVersionKey(?string $rawVersion): ?string {
        if ($rawVersion === null || trim($rawVersion) === '') {
            return null;
        }
        $version = trim($rawVersion);
        foreach ($this->versions as $key => $spec) {
            foreach ((array) ($spec['version_prefixes'] ?? []) as $prefix) {
                if (str_starts_with($version, (string) $prefix)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<string>  $reasons
     */
    private function checkRequiredColumns(SchemaReader $reader, array $spec, array &$reasons): void {
        /** @var array<string, list<string>> $required */
        $required = (array) ($spec['required_columns'] ?? []);
        foreach ($required as $table => $columns) {
            $present = $reader->columnsOf($table);
            if ($present === []) {
                $reasons[] = 'Pflichttabelle fehlt: ' . $table;

                continue;
            }
            $missing = array_values(array_diff($columns, $present));
            if ($missing !== []) {
                $reasons[] = 'Pflichtspalten fehlen in ' . $table . ': ' . implode(', ', $missing);
            }
        }
    }

    /**
     * @param  list<string>  $reasons
     */
    private function checkClockDrift(SchemaReader $reader, ?int $nowUnix, array &$reasons): void {
        $serverTime = $reader->serverTime();
        if ($serverTime === null || $nowUnix === null) {
            return;
        }
        if (abs($serverTime->getTimestamp() - $nowUnix) > $this->maxClockDriftSeconds) {
            $reasons[] = 'Zeitdifferenz zur InvoicePlane-Datenbank überschreitet ' . $this->maxClockDriftSeconds . ' s.';
        }
    }

    /**
     * Stabiler Schema-Fingerprint (Version + Präfix + Spaltensignatur der
     * Fingerprint-Tabellen). Ändert sich das externe Schema, ändert sich der
     * Fingerprint — der Bridge-Befehl vergleicht ihn gegen den erwarteten Stand.
     */
    private function fingerprint(SchemaReader $reader, ?string $versionKey): string {
        $parts = [
            'version' => (string) $versionKey,
            'prefix' => $reader->tablePrefix(),
            'charset' => (string) $reader->charset(),
        ];
        $tables = $versionKey !== null
            ? (array) ($this->versions[$versionKey]['fingerprint_tables'] ?? [])
            : [];
        foreach ($tables as $table) {
            $columns = $reader->columnsOf((string) $table);
            sort($columns);
            $parts['t:' . $table] = implode(',', $columns);
        }

        return CryptoHelper::hash((string) json_encode($parts));
    }
}
