<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/**
 * Eine erwartete Abrechnungsperiode einer Position: für jeden Rhythmus-Schritt
 * zwischen Beginn und Vertragsende muss der Endkunde einmal berechnet worden
 * sein. Ende ist einschließlich.
 */
final readonly class BillingPeriod {
    public function __construct(
        public MarketplaceEntitlement $entitlement,
        public int $index,
        public CarbonImmutable $startsOn,
        public CarbonImmutable $endsOn,
        public int $quantity,
        public Money $unitFee,
    ) {}

    public function fee(): Money {
        return $this->entitlement->fee;
    }

    public function label(): string {
        return $this->startsOn->format('d.m.Y') . ' – ' . $this->endsOn->format('d.m.Y');
    }
}
