<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatPreviewBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\Finance\{AccountType, PostingAccountRole, SettlementKind};
use App\Models\Accounting\{AccountingAccount, AccountingOpenItem, AccountingPostingRule, AccountingTaxCode};
use App\Models\Organization;
use App\Services\Accounting\TaxationMethodResolver;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;

/**
 * Umsatzsteuer-Vorschau: Bemessungsgrundlagen und Steuer je Steuerkonto
 * (Feature 125, MVP-676).
 *
 * Eine **prüfbare Vorschau**, keine Erklärung — sie kennzeichnet Methode,
 * Zeitraum und ungeklärte Fälle.
 */
class VatPreviewBuilder extends AbstractAccountingReportBuilder {
    public function __construct(private readonly TaxationMethodResolver $taxation) {}

    /**
     * @return array{rows: list<array<string, mixed>>, method: \App\Enums\Finance\TaxationMethod, output: numeric-string, input: numeric-string, payable: numeric-string}
     */
    public function build(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $method = $this->taxation->at($organization, $to);
        $rows = [];
        $output = '0.00';
        $input = '0.00';

        // Steuerkonten sind die, die als solche BENANNT sind: über ein
        // Steuerkennzeichen oder eine Buchungsregel mit Steuerrolle. Aus der
        // Kontoart allein ließe sich das nicht ableiten — nicht jedes
        // Passivkonto ist Umsatzsteuer.
        $taxAccountIds = AccountingTaxCode::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('tax_account_id')
            ->pluck('tax_account_id')
            ->merge(AccountingPostingRule::query()
                ->where('organization_id', $organization->id)
                ->whereIn('role', [PostingAccountRole::TaxOutput->value, PostingAccountRole::TaxInput->value])
                ->pluck('accounting_account_id'))
            ->unique()
            ->all();

        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $taxAccountIds)
            ->orderBy('number')
            ->get();

        $sums = $this->sumsByAccount($organization, $from, $to);

        // Ist-Versteuerung (§ 20 UStG): Die Umsatzsteuer entsteht erst mit der
        // Vereinnahmung — also zählen die Ausgleiche, nicht die Belegbuchung.
        // Die Vorsteuer bleibt davon unberührt und kommt weiter aus den
        // Buchungen.
        $collected = $method->followsPayments()
            ? $this->collectedOutputTax($organization, $from, $to)
            : [];

        foreach ($accounts as $account) {
            $debit = $sums[$account->id]['debit'] ?? '0.00';
            $credit = $sums[$account->id]['credit'] ?? '0.00';

            // Umsatzsteuer steht im Haben eines Passivkontos, Vorsteuer im Soll
            // eines Aktivkontos — die Kontoart entscheidet, nicht die Nummer.
            $isOutput = $account->type === AccountType::Liability;

            if ($isOutput && $method->followsPayments()) {
                $amount = $collected[$account->id] ?? '0.00';
                if (NumberHelper::isZeroPrecise($amount)) {
                    continue;
                }
            } else {
                if (NumberHelper::isZeroPrecise($debit) && NumberHelper::isZeroPrecise($credit)) {
                    continue;
                }

                $amount = $isOutput ? NumberHelper::subtractPrecise($credit, $debit, 2) : NumberHelper::subtractPrecise($debit, $credit, 2);
            }

            $rows[] = [
                'account' => $account,
                'direction' => $isOutput ? 'output' : 'input',
                'amount' => $amount,
            ];

            $isOutput
                ? $output = NumberHelper::addPrecise($output, $amount, 2)
                : $input = NumberHelper::addPrecise($input, $amount, 2);
        }

        return [
            'rows' => $rows,
            'method' => $method,
            'output' => $output,
            'input' => $input,
            'payable' => NumberHelper::subtractPrecise($output, $input, 2),
        ];
    }

    /**
     * Vereinnahmte Umsatzsteuer je Steuerkonto (Ist-Versteuerung).
     *
     * Der Steueranteil einer Zahlung ist **anteilig**: Eine Teilzahlung trägt
     * nicht den vollen Steuerbetrag des Belegs. Gerechnet wird je Steuerzeile
     * der Ursprungsbuchung mit dem Verhältnis Ausgleich zu Ursprungsbetrag —
     * eine Rundung auf der Summe würde bei mehreren Steuersätzen abweichen.
     *
     * @return array<int, numeric-string> Kontoschlüssel → vereinnahmte Steuer
     */
    private function collectedOutputTax(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $collected = [];

        foreach ($this->settlementsInPeriod($organization, $from, $to) as $settlement) {
            $item = $settlement->openItem;
            $entry = $item?->entry;
            if (! $item instanceof AccountingOpenItem || $entry === null) {
                continue;
            }

            $original = $item->original_amount;
            if ($original === null || $original->isZero()) {
                continue;
            }

            $settled = $settlement->amount ?? Money::zero($item->currency);
            // Eine Rückbuchung nimmt die vereinnahmte Steuer wieder heraus.
            $reversal = $settlement->kind === SettlementKind::Reversal;

            foreach ($entry->lines as $line) {
                $account = $line->account;
                if ($account === null || $account->type !== AccountType::Liability) {
                    continue;
                }

                $tax = $line->signedAmount()->negated();
                if ($tax->isZero()) {
                    continue;
                }

                $share = $this->proRata($tax->getAmount(), $settled, $original);
                $collected[$account->id] = NumberHelper::addPrecise(
                    $collected[$account->id] ?? '0.00',
                    $reversal ? NumberHelper::negatePrecise($share) : $share,
                    2,
                );
            }
        }

        return $collected;
    }
}
