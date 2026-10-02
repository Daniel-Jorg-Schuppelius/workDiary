<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EfbPriceSheetService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Article\CostKind;
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use App\Models\Platform\Organization;
use App\Services\Article\ServiceCalculationService;
use CommonToolkit\ValueObjects\Money;

/**
 * Daten der EFB-Preisblätter des VHB (MVP-1056).
 *
 * Formblatt 221 (Preisermittlung bei Zuschlagskalkulation) kommt aus dem
 * Kalkulationsschema der Organisation (MVP-1055): Mittellohn, lohngebundene
 * Kosten, Lohnnebenkosten, Kalkulationslohn, Zuschläge je Kostenart und daraus
 * der Verrechnungslohn. Formblatt 223 (Aufgliederung der Einheitspreise)
 * kommt aus der EP-Aufgliederung je LV-Position; der Zeitansatz ist der
 * Lohnanteil geteilt durch den Verrechnungslohn.
 */
final class EfbPriceSheetService {
    public function __construct(
        private readonly ServiceCalculationService $calculation,
        private readonly BoqPricingService $pricing,
    ) {}

    /**
     * @return array{
     *   averageWage: Money, wageRelatedPercent: string, wageRelated: Money, ancillary: Money,
     *   calculationWage: Money, labourMarkupPercent: string, labourMarkup: Money, billingWage: Money,
     *   markups: array<string, array{site: string, general: string, risk: string, total: string}>
     * }
     */
    public function form221(Organization $organization): array {
        $scheme = $this->calculation->scheme($organization);
        $average = $this->calculation->averageWage($scheme);
        $wageRelated = $scheme->wage_related_percent->amountOf($average->withScale(4))->withScale(2);
        $calculationWage = $average->plus($wageRelated)->plus($scheme->wage_ancillary_amount->withScale(2));

        $markups = [];
        foreach (CostKind::cases() as $kind) {
            $row = $scheme->markups->first(fn ($m): bool => $m->cost_kind === $kind);
            $markups[$kind->value] = [
                'site' => $row?->site_overhead_percent->getNumericValue() ?? '0.00',
                'general' => $row?->general_overhead_percent->getNumericValue() ?? '0.00',
                'risk' => $row?->risk_profit_percent->getNumericValue() ?? '0.00',
                'total' => $this->calculation->markupFor($scheme, $kind)->getNumericValue(),
            ];
        }
        $labourMarkup = $this->calculation->markupFor($scheme, CostKind::Labour);

        return [
            'averageWage' => $average,
            'wageRelatedPercent' => $scheme->wage_related_percent->getNumericValue(),
            'wageRelated' => $wageRelated,
            'ancillary' => $scheme->wage_ancillary_amount->withScale(2),
            'calculationWage' => $calculationWage,
            'labourMarkupPercent' => $labourMarkup->getNumericValue(),
            'labourMarkup' => $labourMarkup->amountOf($calculationWage->withScale(4))->withScale(2),
            'billingWage' => $labourMarkup->addTo($calculationWage->withScale(4))->withScale(2),
            'markups' => $markups,
        ];
    }

    /**
     * @return array{rows: list<array{item: BoqItem, labour: ?Money, material: ?Money, equipment: ?Money, other: ?Money, hours: ?float, unitPrice: ?Money}>, missing: int}
     */
    public function form223(BillOfQuantity $bill, Money $billingWage): array {
        $kinds = $this->pricing->componentKinds($bill);
        $rows = [];
        $missing = 0;
        $items = $bill->items()->orderBy('position')->orderBy('id')->get();
        foreach ($items as $item) {
            if (! $item->type->isPriceable() || $item->not_offered) {
                continue;
            }
            $columns = ['labour' => null, 'material' => null, 'equipment' => null, 'other' => null];
            $components = (array) ($item->unit_price_components ?? []);
            if ($components === []) {
                $missing++;
            }
            $currency = $item->currency ?? \CommonToolkit\Enums\CurrencyCode::Euro;
            foreach (array_values($components) as $i => $value) {
                $kind = $kinds[$i] ?? CostKind::Other;
                $column = match ($kind) {
                    CostKind::Labour => 'labour',
                    CostKind::Material => 'material',
                    CostKind::Equipment => 'equipment',
                    default => 'other',
                };
                $amount = Money::of((string) $value, $currency, 4)->withScale(2);
                $columns[$column] = $columns[$column] === null ? $amount : $columns[$column]->plus($amount);
            }
            $rows[] = [
                'item' => $item,
                ...$columns,
                'hours' => $columns['labour'] !== null && ! $billingWage->isZero()
                    ? round($columns['labour']->toFloat() / $billingWage->toFloat(), 3)
                    : null,
                'unitPrice' => $item->unit_price?->withScale(2),
            ];
        }

        return ['rows' => $rows, 'missing' => $missing];
    }
}
