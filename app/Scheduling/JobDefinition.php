<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobDefinition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use App\Support\Trans;
use InvalidArgumentException;

/**
 * Allowlist-Eintrag der Scheduler-Job-Registry (Feature 067, MVP-175).
 * Nur hier deklarierte Artisan-Kommandos sind planbar; die UI kann
 * ausschließlich innerhalb von allowedCadences umplanen. Kein UI-Pfad
 * führt freie Kommandos oder Klassen aus.
 */
final readonly class JobDefinition {
    /**
     * @param list<CadenceType> $allowedCadences
     * @param list<string> $dependsOn Job-Keys (Doku/Warnhinweis, keine harte Verkettung im MVP)
     */
    public function __construct(
        public string $key,
        public string $command,
        public Cadence $defaultCadence,
        public array $allowedCadences,
        public JobCriticality $criticality,
        public bool $onOneServer = true,
        public bool $withoutOverlapping = true,
        public int $expectedRuntimeMinutes = 5,
        public bool $runsInMaintenance = true,
        public bool $perOrganization = false,
        public ?string $cadenceSettingKey = null, // Registry-Setting, das die dailyAt-Zeit liefert (z. B. archive.schedule_at)
        public ?string $plugin = null, // Plugin-Bindung: Watchdog meldet nur bei irgendwo aktivem Plugin ('*' = mind. ein Plugin aktiv)
        public array $dependsOn = [],
        // Lange Läufe als Hintergrundprozess, damit sie die minütlichen Jobs
        // desselben schedule:run-Ticks nicht verzögern (Vollscan 2026-08-23, J8).
        public bool $runInBackground = false,
    ) {
        if ($allowedCadences === []) {
            throw new InvalidArgumentException("Job [{$key}] braucht mindestens eine erlaubte Kadenz.");
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(string $key, array $data): self {
        $allowed = array_map(
            static fn(string $type): CadenceType => CadenceType::from($type),
            (array) ($data['allowed'] ?? []),
        );

        return new self(
            key: $key,
            command: (string) ($data['command'] ?? ''),
            defaultCadence: Cadence::fromArray((array) ($data['cadence'] ?? [])),
            allowedCadences: array_values($allowed),
            criticality: JobCriticality::from((string) ($data['criticality'] ?? 'core')),
            onOneServer: (bool) ($data['on_one_server'] ?? true),
            withoutOverlapping: (bool) ($data['without_overlapping'] ?? true),
            expectedRuntimeMinutes: (int) ($data['expected_runtime_minutes'] ?? 5),
            runsInMaintenance: (bool) ($data['runs_in_maintenance'] ?? true),
            perOrganization: (bool) ($data['per_organization'] ?? false),
            cadenceSettingKey: isset($data['cadence_setting_key']) ? (string) $data['cadence_setting_key'] : null,
            plugin: isset($data['plugin']) ? (string) $data['plugin'] : null,
            dependsOn: array_values((array) ($data['depends_on'] ?? [])),
            // Default: alles ab 5 Minuten erwarteter Laufzeit läuft im Hintergrund.
            runInBackground: (bool) ($data['run_in_background'] ?? ((int) ($data['expected_runtime_minutes'] ?? 5) >= 5)),
        );
    }

    public function allowsCadence(CadenceType $type): bool {
        return in_array($type, $this->allowedCadences, true);
    }

    /** Lesbarer Job-Name (scheduler.job.<key>), Fallback = Registry-Key. */
    public function label(): string {
        return Trans::or('scheduler.job.' . $this->key, $this->key);
    }
}
