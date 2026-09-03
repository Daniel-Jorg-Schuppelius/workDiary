<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceCheckBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\{BillingFrequency, ReconciliationStatus};
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/**
 * Stellt je Produkt (Laufzeit × Intervall) gegenüber: den Einkauf der laufenden
 * Verträge, den aktuellen Listenpreis und die Hersteller-UVP aus der
 * Preisliste sowie die Verkaufspreise je Stück, die der Abgleich in den
 * eigenen Rechnungspositionen gefunden hat. Daraus folgt die Marge gegen den
 * Listenpreis und ein Hinweis, wo der Verkaufspreis unter Einkauf oder UVP
 * liegt bzw. wo ein laufender Vertrag teurer ist als die aktuelle Liste.
 */
final class PriceCheckBuilder {
    public function __construct(
        private readonly ProductNameMatcher $matcher = new ProductNameMatcher(),
    ) {}

    /**
     * @param  list<MarketplaceEntitlement>  $entitlements
     * @return list<PriceCheckRow>
     */
    public function build(array $entitlements, PriceList $priceList, ReconciliationReport $report, CarbonImmutable $reference): array {
        $catalog = UnitPriceCatalog::fromEntitlements($entitlements);

        /** @var array<string, array{product: string, term: int, interval: BillingFrequency, quantity: int, units: list<Money>}> $groups */
        $groups = [];
        foreach ($entitlements as $entitlement) {
            if (! $entitlement->isRunningOn($reference)) {
                continue;
            }
            $key = $this->matcher->productKey($entitlement->edition) . '|' . $entitlement->termMonths() . '|' . $entitlement->frequency->value;
            $groups[$key] ??= ['product' => $entitlement->edition, 'term' => $entitlement->termMonths(), 'interval' => $entitlement->frequency, 'quantity' => 0, 'units' => []];
            $groups[$key]['quantity'] += $catalog->quantityOf($entitlement);
            $groups[$key]['units'][] = $catalog->unitPriceOf($entitlement);
            // Aktueller Vertragsname gewinnt (Quality Hosting vor Telekom).
            if ($entitlement->source === MarketplaceEntitlement::SOURCE_QUALITYHOSTING) {
                $groups[$key]['product'] = $entitlement->edition;
            }
        }

        $sales = $this->salesByProduct($report);

        $rows = [];
        foreach ($groups as $group) {
            $productKey = $this->matcher->productKey($group['product']);
            $entry = $priceList->find($group['product'], $group['term'], $group['interval']);
            $samples = $sales[$productKey] ?? [];
            usort($samples, static fn(Money $a, Money $b): int => $a->compareTo($b));

            $median = $samples === [] ? null : $samples[intdiv(count($samples), 2)];
            $listPrice = $entry?->pricePerInterval;
            $uvp = $entry?->uvpPerInterval;

            $flags = [];
            if ($entry === null) {
                $flags[] = PriceCheckRow::FLAG_NO_LIST;
            }
            if ($samples === []) {
                $flags[] = PriceCheckRow::FLAG_NO_SALES;
            }
            $margin = null;
            if ($median instanceof Money && $listPrice instanceof Money && $listPrice->isPositive()) {
                $margin = round(($median->toFloat() - $listPrice->toFloat()) / $listPrice->toFloat() * 100, 1);
                if ($median->lessThan($listPrice)) {
                    $flags[] = PriceCheckRow::FLAG_BELOW_LIST;
                }
            }
            if ($median instanceof Money && $uvp instanceof Money && $median->lessThan($uvp)) {
                $flags[] = PriceCheckRow::FLAG_BELOW_UVP;
            }
            $contractMax = $group['units'] === [] ? null : Money::max(...$group['units']);
            if ($contractMax instanceof Money && $listPrice instanceof Money && $contractMax->greaterThan($listPrice)) {
                $flags[] = PriceCheckRow::FLAG_CONTRACT_ABOVE_LIST;
            }

            $rows[] = new PriceCheckRow(
                product: $group['product'],
                termMonths: $group['term'],
                interval: $group['interval'],
                runningQuantity: $group['quantity'],
                contractUnitMin: $group['units'] === [] ? null : Money::min(...$group['units']),
                contractUnitMax: $contractMax,
                listPrice: $listPrice,
                uvp: $uvp,
                salesMin: $samples[0] ?? null,
                salesMedian: $median,
                salesMax: $samples === [] ? null : $samples[count($samples) - 1],
                salesSamples: count($samples),
                marginPercent: $margin,
                flags: $flags,
            );
        }

        usort($rows, static fn(PriceCheckRow $a, PriceCheckRow $b): int => strcmp($a->product, $b->product) ?: $a->termMonths <=> $b->termMonths);

        return $rows;
    }

    /**
     * Verkaufspreise je Stück aus den zugeordneten Rechnungspositionen,
     * gruppiert nach Produktschlüssel.
     *
     * @return array<string, list<Money>>
     */
    private function salesByProduct(ReconciliationReport $report): array {
        $sales = [];
        foreach ($report->findings() as $finding) {
            if (! in_array($finding->status, [ReconciliationStatus::Covered, ReconciliationStatus::Underpriced, ReconciliationStatus::Partial], true)) {
                continue;
            }
            $key = $this->matcher->productKey($finding->period->entitlement->edition);
            foreach ($finding->matches as $match) {
                if ($match['quantity'] <= 0.0) {
                    continue;
                }
                $sales[$key][] = $match['line']->unitNet;
            }
        }

        return $sales;
    }
}
