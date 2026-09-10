<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePriceCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\BillingFrequency;
use App\Models\Reselling\{ResalePriceEntry, ResaleSubscription};
use App\Services\Reselling\Marketplace\ProductNameMatcher;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Support\Collection;

/**
 * Preisprüfung (Feature 152, MVP-766 — aus 151 übernommen): je Produkt der
 * Einkauf laut Vertrag, der am Stichtag gültige Katalogpreis (12 Monate,
 * jährlich) und die UVP gegen die Verkaufspreise der Abos; Hinweise, wo der
 * Preis anzupassen ist.
 *
 * @phpstan-type PriceRow array{label: string, currency: CurrencyCode, subscriptions: int, quantity: int, purchase_min: float|null, purchase_max: float|null, list_price: float|null, uvp: float|null, sale_min: float|null, sale_median: float|null, sale_max: float|null, margin: float|null, flags: list<string>}
 */
final class ResalePriceCheck {
    /** Toleranz beim Vergleich Vertrag gegen Katalog (Cent-Rundung der Preislisten). */
    private const TOLERANCE = 0.01;

    /**
     * @return array{rows: list<PriceRow>, catalog_date: CarbonImmutable|null}
     */
    public function build(CarbonImmutable $today): array {
        $subscriptions = ResaleSubscription::query()->planning()->where('is_own_holding', false)->with('lexofficeArticle')->get();
        $catalog = ResalePriceEntry::query()->validOn($today)->where('term_months', 12)->where('interval', BillingFrequency::Yearly->value)->get();
        $matcher = new ProductNameMatcher;
        $rows = [];
        foreach ($subscriptions->groupBy(static fn(ResaleSubscription $s): string => $s->productKey()) as $group) {
            /** @var ResaleSubscription $first */
            $first = $group->first();
            $label = $first->lexofficeArticle !== null ? $first->lexofficeArticle->name : $first->label;
            $purchases = $group->map(static fn(ResaleSubscription $s): ?float => $s->purchase_unit_price?->toFloat())->filter()->values();
            $sales = $group->map(static fn(ResaleSubscription $s): ?float => $s->sale_unit_price?->toFloat())->filter()->sort()->values();
            $entry = $catalog->first(static fn(ResalePriceEntry $e): bool => ProductNameMatcher::normalize($e->product) === ProductNameMatcher::normalize($label))
                ?? $catalog->first(static fn(ResalePriceEntry $e): bool => $matcher->matches($label, $e->product));
            $listPrice = $entry?->purchase_unit_price->toFloat();
            $uvp = $entry?->list_unit_price?->toFloat();
            $median = $sales->isEmpty() ? null : (float) $sales->get(intdiv($sales->count(), 2));
            $rows[] = [
                'label' => $label,
                'currency' => $first->currency,
                'subscriptions' => $group->count(),
                'quantity' => (int) $group->sum('quantity'),
                'purchase_min' => $purchases->isEmpty() ? null : (float) $purchases->min(),
                'purchase_max' => $purchases->isEmpty() ? null : (float) $purchases->max(),
                'list_price' => $listPrice,
                'uvp' => $uvp,
                'sale_min' => $sales->isEmpty() ? null : (float) $sales->first(),
                'sale_median' => $median,
                'sale_max' => $sales->isEmpty() ? null : (float) $sales->last(),
                'margin' => $median !== null && $purchases->isNotEmpty() ? $median - (float) $purchases->max() : null,
                'flags' => self::flags($median, $purchases, $uvp, $listPrice, $sales->isEmpty()),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => count($b['flags']) <=> count($a['flags']) ?: strcmp($a['label'], $b['label']));

        return ['rows' => $rows, 'catalog_date' => $catalog->max('valid_from')];
    }

    /**
     * @param  Collection<int, float>  $purchases
     * @return list<string>
     */
    private static function flags(?float $median, Collection $purchases, ?float $uvp, ?float $listPrice, bool $noSales): array {
        $flags = [];
        if ($median !== null && $purchases->isNotEmpty() && $median < (float) $purchases->max()) {
            $flags[] = 'below_purchase';
        }
        if ($median !== null && $uvp !== null && $median < $uvp) {
            $flags[] = 'below_list';
        }
        if ($listPrice !== null && $purchases->isNotEmpty() && (float) $purchases->max() > $listPrice + self::TOLERANCE) {
            $flags[] = 'contract_above_catalog';
        }
        if ($noSales) {
            $flags[] = 'no_sales';
        }

        return $flags;
    }
}
