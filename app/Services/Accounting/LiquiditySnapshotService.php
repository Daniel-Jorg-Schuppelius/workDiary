<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquiditySnapshotService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\TransactionDirection;
use App\Models\Finance\{BankTransaction, LiquidityForecastSnapshot};
use App\Models\Platform\Organization;
use App\Services\Accounting\Reports\LiquidityForecastBuilder;
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Plan/Ist der Liquiditätsvorschau (MVP-984): Der Wochenstand der Basis-
 * vorschau wird festgehalten (einmal je Kalenderwoche, erneut überschreibt)
 * und später mit den tatsächlichen Kontobewegungen derselben Wochen verglichen.
 */
final class LiquiditySnapshotService {
    public function __construct(
        private readonly LiquidityForecastBuilder $forecasts,
        private readonly JournalService $journal,
    ) {}

    public function take(Organization $organization, CarbonImmutable $asOf): LiquidityForecastSnapshot {
        $data = $this->forecasts->build($organization, $asOf);
        $weeks = array_map(static fn (array $bucket): array => [
            'from' => $bucket['from']->toDateString(),
            'to' => $bucket['to']->toDateString(),
            'label' => $bucket['label'],
            'inflow' => $bucket['inflow'],
            'outflow' => $bucket['outflow'],
            'net' => $bucket['net'],
        ], $data['buckets']);

        return LiquidityForecastSnapshot::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'taken_on' => $asOf->startOfWeek(CarbonInterface::MONDAY)->toDateString()],
            ['opening_balance' => $data['opening_balance'], 'weeks' => $weeks],
        );
    }

    /**
     * Geplante gegen tatsächliche Zahlungsströme je Woche in der Basiswährung
     * (die Vorschau rechnet nur in ihr); Wochen in der Zukunft haben noch kein Ist.
     *
     * @return list<array{label: string, from: string, to: string, planned_in: Money, planned_out: Money, planned_net: Money, actual_in: Money|null, actual_out: Money|null, actual_net: Money|null, deviation: Money|null}>
     */
    public function compare(LiquidityForecastSnapshot $snapshot, CarbonImmutable $today): array {
        $currency = $this->journal->baseCurrency($snapshot->organization()->firstOrFail());
        $rows = [];
        foreach ($snapshot->weeks as $week) {
            $plannedNet = Money::of($week['net'], $currency);
            $row = [
                'label' => $week['label'], 'from' => $week['from'], 'to' => $week['to'],
                'planned_in' => Money::of($week['inflow'], $currency), 'planned_out' => Money::of($week['outflow'], $currency), 'planned_net' => $plannedNet,
                'actual_in' => null, 'actual_out' => null, 'actual_net' => null, 'deviation' => null,
            ];
            if (CarbonImmutable::parse($week['from'])->lessThanOrEqualTo($today)) {
                $in = $this->sum($snapshot->organization_id, $week, TransactionDirection::Credit, $currency);
                $out = $this->sum($snapshot->organization_id, $week, TransactionDirection::Debit, $currency);
                $net = $in->minus($out);
                $row = ['actual_in' => $in, 'actual_out' => $out, 'actual_net' => $net, 'deviation' => $net->minus($plannedNet)] + $row;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param array{from: string, to: string} $week */
    private function sum(int $organizationId, array $week, TransactionDirection $direction, CurrencyCode $currency): Money {
        $sum = BankTransaction::query()->where('organization_id', $organizationId)
            ->where('currency', $currency->value)
            ->where('direction', $direction->value)
            ->whereBetween('booking_date', DateRange::days($week['from'], $week['to']))
            ->sum('amount');

        return Money::of((string) $sum, $currency);
    }
}
