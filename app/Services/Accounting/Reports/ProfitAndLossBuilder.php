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
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Ergebnisrechnung nach Kontengruppen — ausdrücklich keine testierte GuV
 * (Feature 125, MVP-676).
 */
class ProfitAndLossBuilder extends AbstractAccountingReportBuilder {
    /**
     * @return array{income: list<array<string, mixed>>, expense: list<array<string, mixed>>, income_total: string, expense_total: string, result: string}
     */
    public function build(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $sums = $this->sumsByAccount($organization, $from, $to);
        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereIn('type', [AccountType::Income->value, AccountType::Expense->value])
            ->orderBy('number')
            ->get();

        $income = [];
        $expense = [];
        $incomeTotal = '0.00';
        $expenseTotal = '0.00';

        foreach ($accounts as $account) {
            $debit = $sums[$account->id]['debit'] ?? '0.00';
            $credit = $sums[$account->id]['credit'] ?? '0.00';
            if (NumberHelper::isZeroPrecise($debit) && NumberHelper::isZeroPrecise($credit)) {
                continue;
            }

            if ($account->type === AccountType::Income) {
                $amount = NumberHelper::subtractPrecise($credit, $debit, 2);
                $income[] = ['account' => $account, 'amount' => $amount];
                $incomeTotal = NumberHelper::addPrecise($incomeTotal, $amount, 2);

                continue;
            }

            $amount = NumberHelper::subtractPrecise($debit, $credit, 2);
            $expense[] = ['account' => $account, 'amount' => $amount];
            $expenseTotal = NumberHelper::addPrecise($expenseTotal, $amount, 2);
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'result' => NumberHelper::subtractPrecise($incomeTotal, $expenseTotal, 2),
        ];
    }
}
