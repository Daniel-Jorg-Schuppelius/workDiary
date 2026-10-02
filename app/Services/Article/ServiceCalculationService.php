<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceCalculationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Article;

use App\Enums\Article\CostKind;
use App\Models\Article\{Article, ArticleCostApproach, CalculationScheme, CalculationSchemeMarkup, WageGroup};
use App\Models\Platform\Organization;
use App\Services\Article\Dto\ServiceCalculation;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Money, Percentage};

/**
 * Kalkulation von Leistungsartikeln (MVP-1055).
 *
 * Einzelkosten der Teilleistung je Kostenart aus den Kostenansätzen:
 *  - Lohn: Minuten ÷ 60 × Stundensatz × Menge; Stundensatz = Lohn der
 *    Lohngruppe (sonst Mittellohn) × (1 + lohngebundene Kosten) + Lohnnebenkosten,
 *  - Material: Einkaufspreis des Komponentenartikels (sonst fester Preis) × Menge,
 *  - Gerät, Sonstiges, Fremdleistung: fester Preis × Menge.
 * Preis je Kostenart = Einzelkosten × (1 + Baustellengemeinkosten + allgemeine
 * Geschäftskosten + Wagnis und Gewinn) — additiv wie im EFB-Formblatt 221.
 * Gerundet wird erst der Preis auf Cent; Einzelkosten bleiben vierstellig.
 */
final class ServiceCalculationService {
    private const COST_SCALE = 4;

    /** Schema der Organisation; ohne gespeichertes Schema eines ohne Zuschläge (es wird beim Lesen nichts angelegt). */
    public function scheme(Organization $organization): CalculationScheme {
        $scheme = CalculationScheme::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->with('markups')->first();

        return $scheme ?? (new CalculationScheme(['organization_id' => $organization->id]))->setRelation('markups', collect());
    }

    /** Mittellohn: gepflegter Wert, sonst nach Kopfzahl gewichteter Lohn der aktiven Lohngruppen. */
    public function averageWage(CalculationScheme $scheme): Money {
        $currency = self::currency($scheme->currency);
        if ($scheme->average_wage_amount !== null) {
            return $scheme->average_wage_amount;
        }
        $groups = WageGroup::query()->withoutGlobalScopes()
            ->where('organization_id', $scheme->organization_id)
            ->where('is_active', true)
            ->get();
        $heads = (int) $groups->sum('headcount');
        if ($heads <= 0) {
            return Money::zero($currency, 2);
        }
        $weighted = Money::sum($groups->map(
            fn (WageGroup $group): Money => $group->hourly_wage_amount->withScale(self::COST_SCALE)->times($group->headcount)
        )->all(), $currency, self::COST_SCALE);

        return $weighted->dividedBy($heads)->withScale(2);
    }

    /** Kalkulationslohn je Stunde vor Zuschlägen: Lohn × (1 + lohngebundene Kosten) + Lohnnebenkosten. */
    public function labourHourCost(CalculationScheme $scheme, ?WageGroup $group = null): Money {
        $wage = ($group !== null ? $group->hourly_wage_amount : $this->averageWage($scheme))->withScale(self::COST_SCALE);

        return $scheme->wage_related_percent->addTo($wage)->plus($scheme->wage_ancillary_amount->withScale(self::COST_SCALE));
    }

    /** Summe der Zuschläge einer Kostenart. */
    public function markupFor(CalculationScheme $scheme, CostKind $kind): Percentage {
        $markup = $scheme->markups->first(fn (CalculationSchemeMarkup $m): bool => $m->cost_kind === $kind);

        return $markup?->totalPercent() ?? Percentage::of('0');
    }

    /** `null`, wenn der Artikel keine Kostenansätze trägt. */
    public function calculate(Article $article): ?ServiceCalculation {
        $article->loadMissing(['costApproaches.componentArticle', 'costApproaches.wageGroup']);
        if ($article->costApproaches->isEmpty()) {
            return null;
        }
        $organization = $article->organization()->withoutGlobalScopes()->first();
        if (! $organization instanceof Organization) {
            return null;
        }
        $scheme = $this->scheme($organization);
        $currency = self::currency($scheme->currency);

        /** @var array<string, Money> $costs */
        $costs = [];
        $minutes = 0.0;
        foreach ($article->costApproaches as $approach) {
            $cost = $this->costOf($scheme, $approach, $currency);
            $key = $approach->cost_kind->value;
            $costs[$key] = isset($costs[$key]) ? $costs[$key]->plus($cost) : $cost;
            if ($approach->cost_kind === CostKind::Labour) {
                $minutes += (float) ($approach->minutes ?? 0) * (float) $approach->quantity;
            }
        }

        $kinds = [];
        $price = Money::zero($currency, 2);
        $labourPrice = Money::zero($currency, 2);
        foreach (CostKind::cases() as $kind) {
            if (! isset($costs[$kind->value])) {
                continue;
            }
            $markup = $this->markupFor($scheme, $kind);
            $kindPrice = $markup->addTo($costs[$kind->value])->withScale(2);
            $kinds[$kind->value] = ['cost' => $costs[$kind->value], 'markup' => $markup, 'price' => $kindPrice];
            $price = $price->plus($kindPrice);
            if ($kind->countsAsLabourCost()) {
                $labourPrice = $labourPrice->plus($kindPrice);
            }
        }
        $share = $price->isZero()
            ? null
            : Percentage::of(NumberHelper::toUSFormat(min(100.0, max(0.0, $labourPrice->toFloat() / $price->toFloat() * 100)), 2));

        return new ServiceCalculation(
            kinds: $kinds,
            cost: Money::sum(array_values($costs), $currency, self::COST_SCALE),
            price: $price,
            labourMinutes: round($minutes, 2),
            labourShare: $share,
        );
    }

    private function costOf(CalculationScheme $scheme, ArticleCostApproach $approach, CurrencyCode $currency): Money {
        $quantity = NumberHelper::normalizeDecimalString((string) $approach->quantity);
        if ($approach->cost_kind === CostKind::Labour) {
            $hours = (float) ($approach->minutes ?? 0) / 60;

            return $this->labourHourCost($scheme, $approach->wageGroup)->times($hours)->times($quantity);
        }
        $unitCost = $approach->cost_kind === CostKind::Material && $approach->componentArticle?->default_purchase_price !== null
            ? $approach->componentArticle->default_purchase_price
            : ($approach->unit_cost_amount ?? Money::zero($currency, self::COST_SCALE));

        return $unitCost->withScale(self::COST_SCALE)->times($quantity);
    }

    private static function currency(?string $code): CurrencyCode {
        return CurrencyCode::tryFrom(strtoupper((string) $code)) ?? CurrencyCode::Euro;
    }
}
