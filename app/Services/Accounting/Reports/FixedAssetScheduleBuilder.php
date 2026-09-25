<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetScheduleBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Accounting\Reports;

use App\Enums\Finance\DepreciationMethod;
use App\Models\Accounting\FixedAsset;
use App\Models\Platform\Organization;
use App\Services\Accounting\DepreciationCalculator;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Anlagenspiegel eines Geschäftsjahres (Feature 133, MVP-890): Entwicklung
 * der Anschaffungskosten (Anfang, Zugänge, Abgänge, Ende) und der
 * kumulierten AfA (Anfang, AfA des Jahres, AfA auf Abgänge, Ende) je Anlage.
 * Grundlage ist der AfA-Plan, nicht das Journal — der Spiegel zeigt, was die
 * Planung ergibt, auch wenn eine Jahres-AfA noch nicht gebucht ist.
 */
final class FixedAssetScheduleBuilder {
    public const COLUMNS = ['cost_start', 'additions', 'disposals', 'cost_end', 'dep_start', 'dep_year', 'dep_disposals', 'dep_end', 'book_start', 'book_end'];

    public function __construct(private readonly DepreciationCalculator $calculator) {}

    /**
     * @return array{year: int, label: string, starts_on: CarbonImmutable, ends_on: CarbonImmutable, rows: list<array{asset: FixedAsset, values: array<string, Money>}>, totals: array<string, Money>}
     */
    public function build(Organization $organization, int $fiscalYear, int $startMonth, CurrencyCode $currency): array {
        $startsOn = CarbonImmutable::parse(sprintf('%04d-%02d-01', $fiscalYear, min(12, max(1, $startMonth))))->startOfDay();
        $endsOn = $startsOn->addYear()->subDay();

        $assets = FixedAsset::query()
            ->where('organization_id', $organization->id)
            ->where('acquired_on', '<', DateRange::dayAfter($endsOn))
            ->where(fn ($q) => $q->whereNull('disposed_on')->orWhere('disposed_on', '>=', $startsOn->toDateString())
                ->orWhere('depreciation_method', DepreciationMethod::Pool->value))
            ->orderBy('asset_no')
            ->get();

        $rows = [];
        foreach ($assets as $asset) {
            $values = $this->values($asset, $fiscalYear, $startsOn, $endsOn, $startMonth);
            if ($values !== null) {
                $rows[] = ['asset' => $asset, 'values' => $values];
            }
        }

        $totals = [];
        foreach (self::COLUMNS as $column) {
            $totals[$column] = Money::sum(array_map(static fn (array $row): Money => $row['values'][$column], $rows), $currency);
        }

        return [
            'year' => $fiscalYear,
            'label' => $startsOn->year === $endsOn->year ? (string) $fiscalYear : $fiscalYear . '/' . $endsOn->year,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /** @return array<string, Money>|null null = im Geschäftsjahr nicht im Bestand */
    private function values(FixedAsset $asset, int $fiscalYear, CarbonImmutable $startsOn, CarbonImmutable $endsOn, int $startMonth): ?array {
        $zero = Money::zero($asset->currency);
        $cost = $asset->acquisition_cost ?? $zero;
        $depBefore = $zero;
        $depYear = $zero;
        $schedule = $this->calculator->scheduleFor($asset, $startMonth);
        foreach ($schedule as $row) {
            if ($row->fiscalYear < $fiscalYear) {
                $depBefore = $depBefore->plus($row->amount);
            } elseif ($row->fiscalYear === $fiscalYear) {
                $depYear = $row->amount;
            }
        }

        $inStockAtStart = $asset->acquiredOn()->lessThan($startsOn);
        // Sammelposten scheiden mit der letzten Rate aus, nicht beim Abgang (MVP-892).
        $last = end($schedule);
        $disposed = $asset->depreciation_method === DepreciationMethod::Pool
            ? ($last !== false ? $last->endsOn : null)
            : $asset->disposedOn();
        if ($disposed !== null && $disposed->lessThan($startsOn)) {
            return null;
        }
        $disposedInYear = $disposed !== null && $disposed->betweenIncluded($startsOn, $endsOn);

        $costStart = $inStockAtStart ? $cost : $zero;
        $additions = $inStockAtStart ? $zero : $cost;
        $disposals = $disposedInYear ? $cost : $zero;
        $depStart = $inStockAtStart ? $depBefore : $zero;
        $depDisposals = $disposedInYear ? $depStart->plus($depYear) : $zero;
        $costEnd = $costStart->plus($additions)->minus($disposals);
        $depEnd = $depStart->plus($depYear)->minus($depDisposals);

        return [
            'cost_start' => $costStart,
            'additions' => $additions,
            'disposals' => $disposals,
            'cost_end' => $costEnd,
            'dep_start' => $depStart,
            'dep_year' => $depYear,
            'dep_disposals' => $depDisposals,
            'dep_end' => $depEnd,
            'book_start' => $costStart->minus($depStart),
            'book_end' => $costEnd->minus($depEnd),
        ];
    }
}
