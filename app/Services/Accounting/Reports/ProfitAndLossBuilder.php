<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProfitAndLossBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\Finance\AccountType;
use App\Models\Accounting\AccountingAccount;
use App\Models\Organization;
use App\Services\Accounting\AccountingBudgetService;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Ergebnisrechnung nach Kontengruppen — ausdrücklich keine testierte GuV
 * (Feature 125, MVP-676). Vergleichsspalten (Vorjahr, Vormonat, Budget) und
 * Kostenstellen-Filter teilt sie sich mit der BWA über die Basisklasse
 * (Feature 142, MVP-709); das Monatsraster gibt es nur in der BWA.
 */
class ProfitAndLossBuilder extends AbstractAccountingReportBuilder {
    public function __construct(private readonly AccountingBudgetService $budgets) {}

    /**
     * @return array{income: list<array<string, mixed>>, expense: list<array<string, mixed>>, income_total: string, expense_total: string, result: string, compare: string, compare_range: array{0: CarbonImmutable, 1: CarbonImmutable}|null, compare_totals: array{income_total: string, expense_total: string, result: string}|null}
     */
    public function build(Organization $organization, CarbonImmutable $from, CarbonImmutable $to, string $compare = self::COMPARE_NONE, ?int $costCenterId = null): array {
        if (! in_array($compare, [self::COMPARE_PREVIOUS_YEAR, self::COMPARE_PREVIOUS_MONTH, self::COMPARE_BUDGET], true)) {
            $compare = self::COMPARE_NONE;
        }

        $sums = $this->sumsByAccount($organization, $from, $to, null, $costCenterId);
        $compareRange = $this->comparisonRange($from, $to, $compare);
        $compareSums = $compareRange !== null ? $this->sumsByAccount($organization, $compareRange[0], $compareRange[1], null, $costCenterId) : null;
        $planned = $compare === self::COMPARE_BUDGET ? $this->budgets->plannedByAccount($organization, $from, $to, $costCenterId) : null;
        $hasCompare = $compareSums !== null || $planned !== null;

        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereIn('type', [AccountType::Income->value, AccountType::Expense->value])
            ->orderBy('number')
            ->get();

        $income = [];
        $expense = [];
        $totals = ['income_total' => '0.00', 'expense_total' => '0.00'];
        $compareTotals = ['income_total' => '0.00', 'expense_total' => '0.00'];

        foreach ($accounts as $account) {
            $amount = $this->naturalAmount($account, $sums[$account->id] ?? null);
            $compareAmount = $planned !== null
                ? ($planned[$account->id] ?? '0.00')
                : ($compareSums !== null ? $this->naturalAmount($account, $compareSums[$account->id] ?? null) : '0.00');

            if (NumberHelper::isZeroPrecise($amount) && NumberHelper::isZeroPrecise($compareAmount)) {
                continue;
            }

            $row = ['account' => $account, 'amount' => $amount];
            if ($hasCompare) {
                $row += ['compare' => $compareAmount] + $this->deltaOf($amount, $compareAmount);
            }

            $side = $account->type === AccountType::Income ? 'income' : 'expense';
            if ($side === 'income') {
                $income[] = $row;
            } else {
                $expense[] = $row;
            }
            $totals[$side . '_total'] = NumberHelper::addPrecise($totals[$side . '_total'], $amount, 2);
            $compareTotals[$side . '_total'] = NumberHelper::addPrecise($compareTotals[$side . '_total'], $compareAmount, 2);
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'income_total' => $totals['income_total'],
            'expense_total' => $totals['expense_total'],
            'result' => NumberHelper::subtractPrecise($totals['income_total'], $totals['expense_total'], 2),
            'compare' => $compare,
            'compare_range' => $compareRange,
            'compare_totals' => $hasCompare ? [
                'income_total' => $compareTotals['income_total'],
                'expense_total' => $compareTotals['expense_total'],
                'result' => NumberHelper::subtractPrecise($compareTotals['income_total'], $compareTotals['expense_total'], 2),
            ] : null,
        ];
    }
}
