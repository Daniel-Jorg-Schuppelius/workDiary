<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationOptions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use Carbon\CarbonImmutable;

/**
 * Stichtag und Suchfenster: Eine Rechnung gilt als zur Periode gehörig, wenn
 * ihr Datum zwischen `windowBefore` Tagen vor und `windowAfter` Tagen nach dem
 * Periodenbeginn liegt (Vorab- bzw. Nachberechnung).
 */
final readonly class ReconciliationOptions {
    public function __construct(
        public CarbonImmutable $reference,
        public int $windowBefore = 45,
        public int $windowAfter = 90,
    ) {}
}
