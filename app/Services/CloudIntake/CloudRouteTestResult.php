<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudRouteTestResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CloudIntake;

/** Ergebnis des Regeltests einer Ordnerregel (MVP-1613): Vorschau-Zeilen und, bei Fehlschlag, das betroffene Formularfeld. */
final class CloudRouteTestResult {
    /** @param list<string> $lines */
    public function __construct(
        public readonly bool $ok,
        public readonly string $summary,
        public readonly array $lines,
        public readonly ?string $field = null,
    ) {}
}
