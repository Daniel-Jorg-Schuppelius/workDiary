<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSuccessionLinker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use Carbon\CarbonImmutable;

/**
 * Erkennt, welche Telekom-Position von welchem Quality-Hosting-Vertrag
 * abgelöst wurde, und kappt die Telekom-Laufzeit am Vertragsstart.
 *
 * Hintergrund: Bei der Migration vom Telekom Cloud Marketplace zu Quality
 * Hosting wurde jedes Abo zu seinem Jahrestag im neuen System neu angelegt;
 * der Telekom-Export zeigt die alte Position weiter mit dem ursprünglich
 * geplanten Ende (CANCELLED, Ende 2026/2027). Ohne Kappung entstünde für
 * jede Migration eine doppelte Periode, die der Abgleich als „fehlt" meldete.
 *
 * Regel je Telekom-Position: gleiche Firma (normalisierter Name), gleiches
 * Produkt, gleiche Menge, QH-Start zwischen Telekom-Beginn (drei Tage
 * Spielraum) und Telekom-Ende, und der Tag/Monat des QH-Starts liegt am
 * Telekom-Ende (Co-Term-Jahrestag, zehn Tage Toleranz). Jeder QH-Vertrag
 * löst höchstens eine Position ab; bei mehreren Kandidaten gewinnt der
 * nächste Jahrestag.
 */
final class ContractSuccessionLinker {
    private const ANNIVERSARY_TOLERANCE_DAYS = 10;

    private const START_SLACK_DAYS = 3;

    public function __construct(
        private readonly ProductNameMatcher $matcher = new ProductNameMatcher(),
    ) {}

    /**
     * @param  list<MarketplaceEntitlement>  $entitlements
     * @return array{entitlements: list<MarketplaceEntitlement>, links: list<SuccessionLink>}
     */
    public function link(array $entitlements): array {
        $catalog = UnitPriceCatalog::fromEntitlements($entitlements);

        $successors = [];
        foreach ($entitlements as $index => $entitlement) {
            if ($entitlement->source === MarketplaceEntitlement::SOURCE_QUALITYHOSTING) {
                $successors[$index] = $entitlement;
            }
        }
        if ($successors === []) {
            return ['entitlements' => $entitlements, 'links' => []];
        }

        $predecessorIndexes = [];
        foreach ($entitlements as $index => $entitlement) {
            if ($entitlement->source === MarketplaceEntitlement::SOURCE_TELEKOM && $entitlement->endsOn !== null) {
                $predecessorIndexes[] = $index;
            }
        }
        usort($predecessorIndexes, static fn(int $a, int $b): int => $entitlements[$a]->endsOn <=> $entitlements[$b]->endsOn);

        $used = [];
        $links = [];
        $result = $entitlements;

        foreach ($predecessorIndexes as $index) {
            $predecessor = $entitlements[$index];
            /** @var CarbonImmutable $end */
            $end = $predecessor->endsOn;
            $company = $predecessor->company->normalizedName();
            $product = $this->matcher->productKey($predecessor->edition);
            $quantity = $catalog->quantityOf($predecessor);
            $earliestStart = $predecessor->startsOn->subDays(self::START_SLACK_DAYS);

            $best = null;
            foreach ($successors as $successorIndex => $successor) {
                if (isset($used[$successorIndex])) {
                    continue;
                }
                if ($successor->company->normalizedName() !== $company || $this->matcher->productKey($successor->edition) !== $product) {
                    continue;
                }
                if ($catalog->quantityOf($successor) !== $quantity) {
                    continue;
                }
                if ($successor->startsOn->lessThan($earliestStart) || $successor->startsOn->greaterThan($end)) {
                    continue;
                }
                $anniversaryDistance = $this->anniversaryDistance($successor->startsOn, $end);
                if ($anniversaryDistance > self::ANNIVERSARY_TOLERANCE_DAYS) {
                    continue;
                }
                $gap = (int) $successor->startsOn->diffInDays($end);
                if ($best === null || [$anniversaryDistance, $gap] < [$best['distance'], $best['gap']]) {
                    $best = ['index' => $successorIndex, 'distance' => $anniversaryDistance, 'gap' => $gap];
                }
            }

            if ($best === null) {
                continue;
            }

            $successor = $successors[$best['index']];
            $used[$best['index']] = true;
            $cappedEnd = $successor->startsOn->greaterThan($predecessor->startsOn) ? $successor->startsOn : $predecessor->startsOn;
            $note = sprintf('abgelöst durch %s %s ab %s', $successor->sourceLabel(), $successor->entitlementId, $successor->startsOn->format('d.m.Y'));
            $result[$index] = $predecessor->withEndsOn($cappedEnd, $note);
            $links[] = new SuccessionLink($result[$index], $successor);
        }

        return ['entitlements' => array_values($result), 'links' => $links];
    }

    /**
     * Abstand zweier Jahrestage in Tagen, über den Jahreswechsel hinweg.
     */
    private function anniversaryDistance(CarbonImmutable $a, CarbonImmutable $b): int {
        $distance = abs($a->dayOfYear - $b->dayOfYear);

        return min($distance, 366 - $distance);
    }
}
