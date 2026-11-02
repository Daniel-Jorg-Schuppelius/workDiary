<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling\Contracts;

/**
 * Vorprüfung eines Registry-Jobs im `schedule:run`-Prozess: eine billige
 * Abfrage entscheidet, ob der Job überhaupt einen Prozess wert ist
 * (Leistungsanalyse 2026-11-02, perf-02). Ein übersprungener Lauf wird als
 * `skipped` protokolliert und gilt dem Wächter als erfüllt.
 */
interface JobGuard {
    public function shouldRun(): bool;
}
