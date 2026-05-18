<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContinuedPaymentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support\Sickness;

use Carbon\CarbonImmutable;

/**
 * DTO für den Lohnfortzahlungs-Status (§ 3 EntgFG) eines Mitarbeiters
 * zu einem Stichtag. Alle Tag-Werte sind Kalendertage.
 */
final readonly class ContinuedPaymentStatus
{
    public function __construct(
        public int $entitlementDays,
        public int $usedDays,
        public int $remainingDays,
        public ?CarbonImmutable $chainStart,
        public ?CarbonImmutable $exhaustionDate,
        public bool $exhausted,
    ) {}

    public function usedPercent(): int
    {
        if ($this->entitlementDays <= 0) {
            return 0;
        }

        return (int) min(100, round($this->usedDays / $this->entitlementDays * 100));
    }
}
