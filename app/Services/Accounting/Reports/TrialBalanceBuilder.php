<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrialBalanceBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Models\Accounting\AccountingAccount;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Summen- und Saldenliste: Vortrag, Periodenbewegung und Saldo je Konto
 * (Feature 125, MVP-676).
 */
class TrialBalanceBuilder extends AbstractAccountingReportBuilder {
    /**
     * @return array{rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function build(Organization $organization, CarbonImmutable $from, CarbonImmutable $to, ?int $costCenterId = null): array {
        $opening = $this->sumsByAccount($organization, null, $from->subDay(), null, $costCenterId);
        $period = $this->sumsByAccount($organization, $from, $to, null, $costCenterId);

        $accountIds = array_unique([...array_keys($opening), ...array_keys($period)]);
        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $accountIds)
            ->orderBy('number')
            ->get()
            ->keyBy('id');

        $rows = [];
        $totals = ['opening' => '0.00', 'debit' => '0.00', 'credit' => '0.00', 'balance' => '0.00'];

        foreach ($accounts as $id => $account) {
            $openDebit = $opening[$id]['debit'] ?? '0.00';
            $openCredit = $opening[$id]['credit'] ?? '0.00';
            $debit = $period[$id]['debit'] ?? '0.00';
            $credit = $period[$id]['credit'] ?? '0.00';

            $openingBalance = NumberHelper::subtractPrecise($openDebit, $openCredit, 2);
            $balance = NumberHelper::addPrecise($openingBalance, NumberHelper::subtractPrecise($debit, $credit, 2), 2);

            $rows[] = [
                'account' => $account,
                'opening' => $openingBalance,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];

            $totals['opening'] = NumberHelper::addPrecise($totals['opening'], $openingBalance, 2);
            $totals['debit'] = NumberHelper::addPrecise($totals['debit'], $debit, 2);
            $totals['credit'] = NumberHelper::addPrecise($totals['credit'], $credit, 2);
            $totals['balance'] = NumberHelper::addPrecise($totals['balance'], $balance, 2);
        }

        return ['rows' => $rows, 'totals' => $totals];
    }
}
