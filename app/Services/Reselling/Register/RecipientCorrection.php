<?php
/*
 * Created on   : Fri Nov 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipientCorrection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Mirror\MirrorLine;
use Carbon\CarbonImmutable;

/**
 * Korrekturblatt und Lizenzbild eines Rechnungsempfängers (Phase 187, MVP-1772/1776):
 * was gutzuschreiben ist (zu viel berechnet), was noch zu berechnen ist (fehlt),
 * was vom Soll abweicht, und der Saldo — dazu Produkt × Lizenzjahr über alle
 * Anbieter. Rechnet nichts neu: liest die Analyse des {@see RecipientReconciler}
 * und die Prüffälle des {@see LinkAudit}.
 *
 * @phpstan-type Cell array{period: ResalePeriod, subscription: ResaleSubscription, state: string}
 * @phpstan-type Sheet array{credit: list<array{line: MirrorLine, reason: string, amount: float}>, bill: list<array{period: ResalePeriod, subscription: ResaleSubscription, amount: float}>, wrong: list<array{period: ResalePeriod, kind: string, diff: float}>, totals: array{credit: float, bill: float, wrong: float, balance: float}, years: list<int>, rows: list<array{label: string, cells: array<int, list<Cell>>}>}
 */
final class RecipientCorrection {
    public const string COVERED = 'covered';

    public const string MISSING = 'missing';

    public const string PARTIAL = 'partial';

    public const string DEVIATING = 'deviating';

    public const string PLANNED = 'planned';

    public const string WAIVED = 'waived';

    /**
     * @param  array<string, mixed>  $analysis  Ergebnis von {@see RecipientReconciler::forCustomer()}
     * @param  list<array<string, mixed>>  $findings  Prüffälle des Empfängers ({@see LinkAudit::forSubscriptions()})
     * @return Sheet
     */
    public static function build(array $analysis, array $findings, CarbonImmutable $today): array {
        $credit = [];
        foreach ($analysis['lines'] as $row) {
            if ($row['surplus'] !== null && in_array($row['surplus'], RecipientReconciler::OVERBILLED, true) && $row['free_amount'] > 0.0) {
                $credit[] = ['line' => $row['line'], 'reason' => $row['surplus'], 'amount' => $row['free_amount']];
            }
        }
        $bill = [];
        foreach ($analysis['periods'] as $row) {
            $amount = $row['period']->openAmount();
            $bill[] = ['period' => $row['period'], 'subscription' => $row['subscription'], 'amount' => $amount !== null ? $amount->toFloat() : 0.0];
        }
        $wrong = [];
        /** @var array<int, true> $deviating */
        $deviating = [];
        foreach ($findings as $finding) {
            if (in_array($finding['kind'], LinkAudit::DEVIATIONS, true) && $finding['period'] instanceof ResalePeriod) {
                $wrong[] = ['period' => $finding['period'], 'kind' => (string) $finding['kind'], 'diff' => (float) $finding['diff']];
                $deviating[(int) $finding['period']->id] = true;
            }
        }
        $totals = [
            'credit' => round((float) array_sum(array_column($credit, 'amount')), 2),
            'bill' => round((float) array_sum(array_column($bill, 'amount')), 2),
            'wrong' => round((float) array_sum(array_column($wrong, 'diff')), 2),
        ];
        // Saldo aus Sicht des Resellers: was noch zu berechnen ist, abzüglich dessen, was zurückgeht.
        $totals['balance'] = round($totals['bill'] + $totals['wrong'] - $totals['credit'], 2);

        $labels = [];
        foreach ($analysis['products'] as $product) {
            $labels[$product['key']] = $product['label'];
        }
        /** @var array<string, array<int, list<Cell>>> $cells */
        $cells = [];
        $years = [];
        foreach ($analysis['subscriptions'] as $subscription) {
            $key = $analysis['product_by_subscription'][$subscription->id] ?? $subscription->productKey();
            foreach ($subscription->periods as $period) {
                $year = (int) $period->starts_on->format('Y');
                $years[$year] = true;
                $cells[$key][$year][] = ['period' => $period, 'subscription' => $subscription, 'state' => self::state($period, $today, isset($deviating[(int) $period->id]))];
            }
        }
        ksort($years);
        $rows = [];
        foreach ($cells as $key => $byYear) {
            $rows[] = ['label' => $labels[$key] ?? $key, 'cells' => $byYear];
        }
        usort($rows, static fn(array $a, array $b): int => strcmp($a['label'], $b['label']));

        return ['credit' => $credit, 'bill' => $bill, 'wrong' => $wrong, 'totals' => $totals, 'years' => array_keys($years), 'rows' => $rows];
    }

    private static function state(ResalePeriod $period, CarbonImmutable $today, bool $deviating): string {
        return match (true) {
            in_array($period->status, [PeriodStatus::Waived, PeriodStatus::Disputed], true) => self::WAIVED,
            $deviating => self::DEVIATING,
            $period->coveredMonths() + 0.001 >= $period->requiredMonths() => self::COVERED,
            $period->starts_on->greaterThan($today) => self::PLANNED,
            $period->coveredMonths() > 0.001 => self::PARTIAL,
            default => self::MISSING,
        };
    }

    /**
     * Zeilen des Korrekturblatts für den Export.
     *
     * @param  Sheet  $sheet
     * @return list<list<string|float>>
     */
    public static function exportRows(array $sheet): array {
        $rows = [];
        foreach ($sheet['credit'] as $entry) {
            $rows[] = [(string) __('resale.correction.credit'), (string) $entry['line']->voucherNumber, (string) ($entry['line']->voucherDate?->format('d.m.Y') ?? ''), $entry['line']->label(), (string) __('resale.board.surplus.' . $entry['reason']), -$entry['amount']];
        }
        foreach ($sheet['bill'] as $entry) {
            $rows[] = [(string) __('resale.correction.bill'), '', $entry['period']->label(), $entry['subscription']->label, $entry['subscription']->holderLabel(), $entry['amount']];
        }
        foreach ($sheet['wrong'] as $entry) {
            $rows[] = [(string) __('resale.correction.wrong'), '', $entry['period']->label(), $entry['period']->subscription->label, (string) __('resale.deviation.kind.' . $entry['kind']), $entry['diff']];
        }
        $rows[] = [(string) __('resale.correction.balance'), '', '', '', '', $sheet['totals']['balance']];

        return $rows;
    }
}
