<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReplacementForecastBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\AssetFinance\AssetFinanceStatus;
use App\Enums\Finance\DepreciationMethod;
use App\Models\Accounting\FixedAsset;
use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\Platform\Organization;
use App\Services\Accounting\DepreciationCalculator;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Restwert- und Ersatzprognose (MVP-908): je Anlage Buchwert zum Stichtag,
 * Ende der Nutzungsdauer und geschätzte Wiederbeschaffung (AK × (1 + p)^Nutzungsjahre,
 * p als Organisationseinstellung), je Leasingvertrag Ende und Restwert —
 * alles, was bis zum Ende des Horizonts ausläuft (auch bereits Überfälliges).
 * Sammelposten sind keine Einzelgüter und bleiben außen vor.
 */
final class ReplacementForecastBuilder {
    public function __construct(private readonly DepreciationCalculator $calculator) {}

    /**
     * @param  numeric-string  $inflationPercent
     * @return array{asOf: CarbonImmutable, until: CarbonImmutable, assets: list<array{asset: FixedAsset, ends_on: CarbonImmutable, book_value: string, replacement: string, overdue: bool}>, leases: list<array{contract: AssetFinanceContract, ends_on: CarbonImmutable, residual: string}>, years: array<int, string>}
     */
    public function build(Organization $organization, CarbonImmutable $asOf, int $horizonYears, string $inflationPercent, int $startMonth = 1): array {
        $until = $asOf->addYears(max(1, $horizonYears))->endOfYear();
        $factor = NumberHelper::addPrecise('1', NumberHelper::dividePrecise($inflationPercent, '100', 8), 8);
        $assets = [];
        $years = [];

        $candidates = FixedAsset::query()
            ->where('organization_id', $organization->id)
            ->whereNull('disposed_on')
            ->where('depreciation_method', '!=', DepreciationMethod::Pool->value)
            ->orderBy('acquired_on')
            ->get();
        foreach ($candidates as $asset) {
            $endsOn = $asset->acquiredOn()->addMonthsNoOverflow($asset->useful_life_months)->subDay();
            if ($endsOn->greaterThan($until)) {
                continue;
            }
            $cost = $asset->acquisition_cost?->getAmount() ?? '0.00';
            $depreciated = '0.00';
            foreach ($this->calculator->scheduleFor($asset, $startMonth) as $row) {
                if ($row->endsOn->lessThanOrEqualTo($asOf)) {
                    $depreciated = NumberHelper::addPrecise($depreciated, $row->amount->getAmount(), 2);
                }
            }
            $age = intdiv($asset->useful_life_months + 6, 12); // Preisstand am Ende der Nutzungsdauer
            $replacement = NumberHelper::roundPrecise(NumberHelper::multiplyPrecise($cost, NumberHelper::powPrecise($factor, (string) $age, 8), 8), 2, RoundingMode::HalfUp);
            $assets[] = ['asset' => $asset, 'ends_on' => $endsOn, 'book_value' => NumberHelper::subtractPrecise($cost, $depreciated, 2), 'replacement' => $replacement, 'overdue' => $endsOn->lessThan($asOf)];
            $bucket = max($asOf->year, $endsOn->year);
            $years[$bucket] = NumberHelper::addPrecise($years[$bucket] ?? '0.00', $replacement, 2);
        }

        $leases = [];
        $contracts = AssetFinanceContract::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [AssetFinanceStatus::Active->value, AssetFinanceStatus::Ending->value, AssetFinanceStatus::Extended->value])
            ->whereNotNull('ends_on')
            ->where('ends_on', '<', DateRange::dayAfter($until))
            ->orderBy('ends_on')
            ->get();
        foreach ($contracts as $contract) {
            $leases[] = ['contract' => $contract, 'ends_on' => CarbonImmutable::parse($contract->ends_on), 'residual' => NumberHelper::roundPrecise((string) ($contract->residual_value ?? '0'), 2)];
        }
        ksort($years);

        return ['asOf' => $asOf, 'until' => $until, 'assets' => $assets, 'leases' => $leases, 'years' => $years];
    }
}
