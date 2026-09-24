<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectEconomicsDimension.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Contracts;

use Carbon\CarbonImmutable;

/**
 * Erweiterungspunkt des Wirtschaftlichkeitsberichts (MVP-863): Ein Fachmodul
 * liefert eine zusätzliche Projektdimension (Bau: LV-Positionen) und meldet
 * sie über `Manifest::extensions()`; der Berichtsrahmen kennt kein Fachmodul.
 */
interface ProjectEconomicsDimension {
    /** Schlüssel der Dimension in Controller und View (z. B. `boq`). */
    public function key(): string;

    /** @return array<string, mixed> Struktur der Dimension, wie die View sie erwartet */
    public function build(CarbonImmutable $from, CarbonImmutable $to, int $projectId): array;
}
