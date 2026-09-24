<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeadlineScanRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Modules\ModuleRegistry;

/**
 * Registry aller Fristen-Scans (Vollscan 2026-08, B11): eine Klasse je
 * Fachmodul, der Command iteriert nur noch.
 *
 * Reihenfolge = historische handle()-Reihenfolge des ScanDeadlinesCommand
 * (Wartung lief dort nach den Vergabefristen, jetzt im Asset-Modul davor).
 * Sie ist NICHT verhaltensrelevant: Dedup/Eskalation laufen pro (Org, Event,
 * Subjekt, Stufe) über das notification_dispatch_log, kein Event/Subjekt-Paar
 * kommt in zwei Scans vor, und Statefortschreibung (escalation_level,
 * last_warned_period) bleibt scan-intern. Festgeschrieben bleibt sie trotzdem
 * — für stabile Logs und vergleichbare Läufe.
 */
/**
 * Terminscans aller Module (MVP-863): jedes Modul meldet seine Scans über
 * `Manifest::extensions()[DeadlineScan::class]`; Reihenfolge = Modulcode,
 * dann Deklaration im Manifest (stabile Log-Reihenfolge).
 */
final class DeadlineScanRegistry {
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<DeadlineScan> */
    public function scans(): array {
        return array_map(
            static fn(string $class): DeadlineScan => app($class),
            $this->modules->extensions(DeadlineScan::class),
        );
    }
}
