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

use App\Enums\Reselling\{BillingFrequency, SubscriptionStatus};
use App\Models\Reselling\{ResalePriceEntry, ResaleSubscription};
use App\Services\Reselling\Marketplace\ProductNameMatcher;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;

/**
 * Preisprüfung (Feature 152, MVP-766 — aus 151 übernommen): je Produkt der
 * Einkauf laut Vertrag, der am Stichtag gültige Katalogpreis (12 Monate,
 * jährlich), die UVP und der Verkaufspreis des lokalen Artikels
 * (`articles.default_sale_price`, Review 2026-09-11 — mit oder ohne
 * Lexoffice-Artikel am Abo) gegen die Verkaufspreise der Abos; Hinweise, wo
 * der Preis anzupassen ist.
 *
 * @phpstan-type PriceRow array{label: string, currency: CurrencyCode, subscriptions: int, quantity: int, purchase_min: float|null, purchase_max: float|null, list_price: float|null, uvp: float|null, article_sale: float|null, sale_min: float|null, sale_median: float|null, sale_max: float|null, margin: float|null, flags: list<string>}
 */
final class ResalePriceCheck {
    /** Toleranz beim Vergleich Vertrag gegen Katalog (Cent-Rundung der Preislisten). */
    private const TOLERANCE = 0.01;

    /**
     * @return array{rows: list<PriceRow>, catalog_date: CarbonImmutable|null}
     */
    public function build(CarbonImmutable $today): array {
        $subscriptions = ResaleSubscription::query()->planning()->where('is_own_holding', false)->with(['lexofficeArticle', 'article'])->get();
        $catalog = ResalePriceEntry::query()->validOn($today)->where('term_months', 12)->where('interval', BillingFrequency::Yearly->value)->get();
        $matcher = new ProductNameMatcher;
        $rows = [];
        foreach ($subscriptions->groupBy(static fn(ResaleSubscription $s): string => $s->productKey()) as $group) {
            /** @var ResaleSubscription $first */
            $first = $group->first();
            $label = self::labelOf($first);
            $purchases = $group->map(static fn(ResaleSubscription $s): ?float => $s->purchase_unit_price?->toFloat())->filter()->values();
            $sales = $group->map(static fn(ResaleSubscription $s): ?float => $s->sale_unit_price?->toFloat())->filter()->sort()->values();
            $entry = self::catalogEntryFor($catalog, $label, $matcher);
            $listPrice = $entry?->purchase_unit_price->toFloat();
            $uvp = $entry?->list_unit_price?->toFloat();
            // Verkaufspreis des lokalen Artikels — erster Artikel der Gruppe mit Preis in der Währung der Abos.
            $articleSale = $group
                ->map(static fn(ResaleSubscription $s): ?Money => $s->article?->default_sale_price)
                ->first(static fn(?Money $price): bool => $price !== null && $price->getCurrency() === $first->currency)
                ?->toFloat();
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
                'article_sale' => $articleSale,
                'sale_min' => $sales->isEmpty() ? null : (float) $sales->first(),
                'sale_median' => $median,
                'sale_max' => $sales->isEmpty() ? null : (float) $sales->last(),
                'margin' => $median !== null && $purchases->isNotEmpty() ? $median - (float) $purchases->max() : null,
                'flags' => self::flags($median, $purchases, $uvp, $listPrice, $articleSale, $sales->isEmpty()),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => count($b['flags']) <=> count($a['flags']) ?: strcmp($a['label'], $b['label']));

        return ['rows' => $rows, 'catalog_date' => $catalog->max('valid_from')];
    }

    /**
     * Aktive Abos, deren am Stichtag gültiger Katalog-Einkaufspreis (Produkt,
     * Laufzeit, Intervall des Abos) vom Vertragspreis abweicht und deren
     * Katalogzeile in den letzten `$days` Tagen angelegt wurde — der Digest
     * meldet so eine neue Preisliste, die Verträge nicht widerspiegeln.
     */
    public function recentCatalogChanges(CarbonImmutable $today, int $days): int {
        $catalog = ResalePriceEntry::query()->validOn($today)->orderByDesc('valid_from')->orderByDesc('id')->get();
        if ($catalog->isEmpty()) {
            return 0;
        }
        $since = now()->subDays($days);
        $matcher = new ProductNameMatcher;
        $count = 0;
        $subscriptions = ResaleSubscription::query()->where('status', SubscriptionStatus::Active->value)->whereNotNull('purchase_unit_price')->with('lexofficeArticle')->get();
        foreach ($subscriptions as $subscription) {
            $purchase = $subscription->purchase_unit_price;
            if ($purchase === null) {
                continue;
            }
            $candidates = $catalog->filter(static fn(ResalePriceEntry $e): bool => $e->term_months === $subscription->term_months && $e->interval === $subscription->interval);
            $entry = self::catalogEntryFor($candidates, self::labelOf($subscription), $matcher);
            if ($entry === null || $entry->created_at === null || $entry->created_at->lessThan($since)) {
                continue;
            }
            if (abs($entry->purchase_unit_price->toFloat() - $purchase->toFloat()) > self::TOLERANCE) {
                $count++;
            }
        }

        return $count;
    }

    /** Produktname des Abos: Lexoffice-Artikel, sonst die Bezeichnung. */
    private static function labelOf(ResaleSubscription $subscription): string {
        return $subscription->lexofficeArticle !== null ? $subscription->lexofficeArticle->name : $subscription->label;
    }

    /**
     * Katalogzeile zum Produkt: exakter normalisierter Name vor Produkterkennung.
     *
     * @param  Collection<int, ResalePriceEntry>  $catalog
     */
    private static function catalogEntryFor(Collection $catalog, string $label, ProductNameMatcher $matcher): ?ResalePriceEntry {
        return $catalog->first(static fn(ResalePriceEntry $e): bool => ProductNameMatcher::normalize($e->product) === ProductNameMatcher::normalize($label))
            ?? $catalog->first(static fn(ResalePriceEntry $e): bool => $matcher->matches($label, $e->product));
    }

    /**
     * @param  Collection<int, float>  $purchases
     * @return list<string>
     */
    private static function flags(?float $median, Collection $purchases, ?float $uvp, ?float $listPrice, ?float $articleSale, bool $noSales): array {
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
        if ($median !== null && $articleSale !== null && abs($median - $articleSale) > self::TOLERANCE) {
            $flags[] = 'article_price_differs';
        }
        if ($noSales) {
            $flags[] = 'no_sales';
        }

        return $flags;
    }
}
