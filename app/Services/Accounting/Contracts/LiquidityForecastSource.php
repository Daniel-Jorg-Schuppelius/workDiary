<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityForecastSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Contracts;

use App\Models\Platform\Organization;
use Carbon\CarbonImmutable;

/**
 * Zusätzliche Planposten für Liquiditätsszenarien (MVP-954), z. B. geplante
 * Investitionen. Fachmodule melden ihre Quelle im Manifest; die Basisvorschau
 * nutzt sie nicht.
 */
interface LiquidityForecastSource {
    /** Schlüssel der Quelle, zugleich Spalte der Vorschau. */
    public function key(): string;

    /** @return list<array{source: string, direction: 'in'|'out', amount: numeric-string, expected_on: CarbonImmutable, label: string, note: ?string}> */
    public function items(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array;
}
