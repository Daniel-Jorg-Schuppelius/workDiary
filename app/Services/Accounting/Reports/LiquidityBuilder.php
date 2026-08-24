<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\Finance\OpenItemDirection;
use App\Models\Accounting\{AccountingAccount, AccountingOpenItem};
use App\Models\Organization;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Decimal;

/**
 * Bank/Kasse und Liquidität (Feature 125, MVP-676): Ist-Salden und erwartete
 * Bewegungen bleiben **getrennt** — eine Summe aus beidem wäre eine Prognose,
 * die aussieht wie ein Kontostand.
 */
class LiquidityBuilder extends AbstractAccountingReportBuilder {
    /**
     * @return array{accounts: list<array<string, mixed>>, cash_total: string, receivable: string, payable: string, forecast: string}
     */
    public function build(Organization $organization, CarbonImmutable $asOf): array {
        $sums = $this->sumsByAccount($organization, null, $asOf);
        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->where(fn ($query) => $query->where('is_bank', true)->orWhere('is_cash', true))
            ->orderBy('number')
            ->get();

        $rows = [];
        $cashTotal = '0.00';
        foreach ($accounts as $account) {
            $balance = NumberHelper::subtractPrecise($sums[$account->id]['debit'] ?? '0.00', $sums[$account->id]['credit'] ?? '0.00', 2);
            $rows[] = ['account' => $account, 'balance' => $balance];
            $cashTotal = NumberHelper::addPrecise($cashTotal, $balance, 2);
        }

        $receivable = $this->openTotal($organization, OpenItemDirection::Receivable);
        $payable = $this->openTotal($organization, OpenItemDirection::Payable);

        return [
            'accounts' => $rows,
            'cash_total' => $cashTotal,
            'receivable' => $receivable,
            'payable' => $payable,
            'forecast' => NumberHelper::subtractPrecise(NumberHelper::addPrecise($cashTotal, $receivable, 2), $payable, 2),
        ];
    }

    /** @return numeric-string */
    private function openTotal(Organization $organization, OpenItemDirection $direction): string {
        $sum = AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->where('direction', $direction->value)
            ->stillOpen()
            ->sum('open_amount');

        return Decimal::of((string) $sum, 2)->getValue();
    }
}
