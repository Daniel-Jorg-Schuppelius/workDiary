<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingPeriodExpander.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use Carbon\CarbonImmutable;
use LogicException;

/**
 * Zerlegt eine Position in ihre Abrechnungsperioden. Ein Rest am Laufzeitende,
 * der kürzer als die Mindestlänge des Rhythmus ist, ist ein Ausrichtungs-
 * Stummel (der Marketplace richtet Laufzeiten am Jahrestag der Erstbestellung
 * aus) und keine eigene Periode.
 */
final class BillingPeriodExpander {
    private const MAX_PERIODS = 600;

    public function __construct(private readonly UnitPriceCatalog $catalog) {}

    /**
     * Alle Perioden einer befristeten Position.
     *
     * @return list<BillingPeriod>
     */
    public function all(MarketplaceEntitlement $entitlement): array {
        if ($entitlement->endsOn === null) {
            throw new LogicException('Eine Position ohne Ende hat keine abgeschlossene Periodenliste — dueUntil() mit Stichtag verwenden.');
        }

        return $this->expand($entitlement, null);
    }

    /**
     * Perioden, deren Beginn am Stichtag erreicht ist — nur die können schon
     * berechnet worden sein. Bei offenem Ende ist der Stichtag die Grenze.
     *
     * @return list<BillingPeriod>
     */
    public function dueUntil(MarketplaceEntitlement $entitlement, CarbonImmutable $reference): array {
        return $this->expand($entitlement, $reference);
    }

    /**
     * @return list<BillingPeriod>
     */
    private function expand(MarketplaceEntitlement $entitlement, ?CarbonImmutable $reference): array {
        $periods = [];
        $start = $entitlement->startsOn;
        $endsOn = $entitlement->endsOn;
        $frequency = $entitlement->frequency;
        $quantity = $this->catalog->quantityOf($entitlement);
        $unitFee = $this->catalog->unitPriceOf($entitlement);

        while (count($periods) < self::MAX_PERIODS) {
            if ($endsOn !== null && ! $start->lessThan($endsOn)) {
                break;
            }
            if ($reference !== null && $start->greaterThan($reference)) {
                break;
            }

            $next = $frequency->advance($start);
            $boundary = $endsOn !== null && $endsOn->lessThan($next) ? $endsOn : $next;
            $end = $boundary->subDay();
            $days = (int) $start->diffInDays($end) + 1;

            if ($endsOn !== null && $next->greaterThan($endsOn) && $days < $frequency->minimumPeriodDays()) {
                break;
            }

            $periods[] = new BillingPeriod($entitlement, count($periods) + 1, $start, $end, $quantity, $unitFee);
            $start = $next;
        }

        return $periods;
    }
}
