<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting\Adapters;

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind, TaxCodeDirection};
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceTaxCase};
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule, AccountingTaxCode};
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine, PostingRuleResolver};
use App\Services\Invoicing\EInvoice\IncomingInvoiceTaxCaseResolver;
use App\Services\Invoicing\TaxResolver;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Eingangsrechnung → Aufwand und Vorsteuer an Verbindlichkeit
 * (Feature 125, MVP-673).
 *
 * Buchungsfähig erst nach **fachlicher Freigabe**: Ein nur eingegangener Beleg
 * ist eine Behauptung des Lieferanten, keine Verbindlichkeit.
 *
 * Je Steuersatz der Aufschlüsselung eine Aufwands- und eine Vorsteuerzeile
 * (MVP-1622), damit Kennziffer 66 und das Skonto je Satz stimmen. Bei
 * Steuerschuld des Empfängers (§ 13b UStG, innergemeinschaftlicher Erwerb,
 * MVP-1621) bucht der Beleg netto: Aufwand an Verbindlichkeit, dazu die selbst
 * berechnete Umsatzsteuer im Haben und dieselbe Vorsteuer im Soll.
 */
class IncomingInvoiceAdapter extends AbstractPostingAdapter {
    public function __construct(
        PostingRuleResolver $rules,
        private readonly IncomingInvoiceTaxCaseResolver $taxCases,
        private readonly TaxResolver $taxes,
    ) {
        parent::__construct($rules);
    }

    public function kind(): PostingSourceKind {
        return PostingSourceKind::IncomingInvoice;
    }

    /** @return Collection<int, Model> */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        /** @var Collection<int, Model> $invoices */
        // Ausgangsbelege aus dem Postfach (MVP-1107) sind keine Verbindlichkeit.
        $invoices = IncomingEInvoice::query()
            ->purchases()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [IncomingEInvoiceStatus::Approved, IncomingEInvoiceStatus::PaymentReleased])
            ->whereNotNull('issue_date')
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->orderBy('issue_date')
            ->get();

        return $invoices;
    }

    public function proposalFor(Organization $organization, Model $source): PostingProposal {
        assert($source instanceof IncomingEInvoice);

        $issuedOn = CarbonImmutable::parse($source->issue_date ?? now())->startOfDay();
        $blockers = [];
        $lines = [];
        $ruleVersions = [];

        if ($this->alreadyHandedOver($source)) {
            $blockers[] = (string) __('accounting.inbox.blocker.handed_over');
        }

        $gross = $source->amount_gross?->getAmount() ?? '0.00';
        $net = $source->amount_net?->getAmount() ?? '0.00';
        $tax = $source->amount_tax?->getAmount() ?? '0.00';

        if (NumberHelper::isZeroPrecise($gross)) {
            $blockers[] = (string) __('accounting.inbox.blocker.no_amount');
        }

        // Netto ohne eigenen Ausweis: Der Beleg trägt nur den Bruttobetrag —
        // dann ist der Aufwand brutto und die Vorsteuer bleibt außen vor.
        if (NumberHelper::isZeroPrecise($net) && NumberHelper::isPositivePrecise($gross)) {
            $net = $gross;
            $tax = '0.00';
        }

        $case = $this->taxCases->resolve($source)['case'];
        $number = (string) $source->invoice_number;
        $currency = $source->currency instanceof CurrencyCode ? $source->currency : CurrencyCode::Euro;
        $seller = ['seller' => (string) ($source->seller_name ?? '')];

        foreach ($this->rateGroups($source, $gross, $net, $tax) as $group) {
            $context = ['tax_case' => $case->value] + ($group['rate'] !== null ? ['tax_rate' => $group['rate']] : []);
            $expense = $this->rule($organization, PostingAccountRole::Expense, $seller + $context, $issuedOn)
                ?? $this->rule($organization, PostingAccountRole::Expense, $context, $issuedOn);
            if ($expense === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Expense);

                continue;
            }
            $ruleVersions[] = $expense->versionTag();

            $groupLines = $case->isSelfAssessed()
                ? $this->selfAssessedLines($organization, $case, $expense, $group, $currency, $issuedOn, $number, $blockers)
                : $this->regularLines($organization, $expense, $group, $issuedOn, $number, $blockers, $ruleVersions);
            array_push($lines, ...$groupLines);
        }

        $payable = $this->rule($organization, PostingAccountRole::Payable, [], $issuedOn);
        if ($payable === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Payable);
        } else {
            $line = $this->line(PostingAccountRole::Payable, $payable, '0.00', $gross, $number);
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $payable->versionTag();
            }
        }

        // Fremdwährung zum Monatskurs (MVP-1012); ohne Kurs bleibt der Beleg in der Inbox.
        $fx = $this->inBaseCurrency($organization, $source->currency, $issuedOn, $lines);
        if ($fx['blocker'] !== null) {
            $blockers[] = $fx['blocker'];
        }

        return new PostingProposal(
            kind: $this->kind(),
            source: $source,
            sourceKey: $this->sourceKey($source),
            bookedOn: $issuedOn,
            memo: (string) __('accounting.inbox.memo.incoming_invoice', [
                'number' => (string) ($source->invoice_number ?? '—'),
                'seller' => (string) ($source->seller_name ?? '—'),
            ]),
            lines: $fx['lines'],
            blockers: array_values(array_unique($blockers)),
            documentOn: $issuedOn,
            documentReference: (string) ($source->invoice_number ?? null),
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: (string) ($source->invoice_number ?? $source->seller_name ?? '—'),
            extra: $fx['extra'] + ['due_date' => $source->due_date?->toDateString(), 'tax_case' => $case->value],
        );
    }

    /**
     * Steuersätze des Belegs (BG-23 bzw. Werte-Dialog). Die Aufschlüsselung
     * trägt die Buchung nur, wenn sie das Brutto ergibt; sonst bleibt es bei
     * einer Zeile aus den Kopfwerten ohne Satz.
     *
     * @param  numeric-string  $gross
     * @param  numeric-string  $net
     * @param  numeric-string  $tax
     * @return list<array{rate: ?numeric-string, net: numeric-string, tax: numeric-string}>
     */
    private function rateGroups(IncomingEInvoice $source, string $gross, string $net, string $tax): array {
        $groups = [];
        foreach ($source->taxBreakdown() as $row) {
            if (! is_numeric($row['net'] ?? null) || ! is_numeric($row['tax'] ?? null) || ! is_numeric($row['percent'] ?? null)) {
                $groups = [];

                break;
            }
            $rate = NumberHelper::roundPrecise((string) $row['percent'], 2);
            $groups[$rate] ??= ['rate' => $rate, 'net' => '0.00', 'tax' => '0.00'];
            $groups[$rate]['net'] = NumberHelper::addPrecise($groups[$rate]['net'], NumberHelper::roundPrecise((string) $row['net'], 2), 2);
            $groups[$rate]['tax'] = NumberHelper::addPrecise($groups[$rate]['tax'], NumberHelper::roundPrecise((string) $row['tax'], 2), 2);
        }

        $sum = NumberHelper::sumPrecise(array_map(static fn (array $group): string => NumberHelper::addPrecise($group['net'], $group['tax'], 2), $groups), 2);
        if ($groups !== [] && NumberHelper::comparePrecise($sum, $gross, 2) === 0) {
            return array_values($groups);
        }

        return [['rate' => null, 'net' => $net, 'tax' => $tax]];
    }

    /**
     * Aufwand mit Kennzeichen und Vorsteuer an der Nettozeile, Vorsteuer auf dem
     * Konto des Satzes. Passt das Kennzeichen der Regel nicht zum Satz des
     * Belegs, gilt das Vorsteuerkennzeichen dieses Satzes.
     *
     * @param  array{rate: ?numeric-string, net: numeric-string, tax: numeric-string}  $group
     * @param  list<string>  $blockers
     * @param  list<string>  $ruleVersions
     * @return list<PostingProposalLine>
     */
    private function regularLines(Organization $organization, AccountingPostingRule $expense, array $group, CarbonImmutable $on, string $number, array &$blockers, array &$ruleVersions): array {
        $taxed = ! NumberHelper::isZeroPrecise($group['tax']);
        $code = null;
        $taxAccount = null;

        if ($taxed) {
            $taxRule = $this->rule($organization, PostingAccountRole::TaxInput, $group['rate'] !== null ? ['tax_rate' => $group['rate']] : [], $on);
            $code = $taxRule?->taxCode;
            $taxAccount = $taxRule?->account;
            if ($group['rate'] !== null && ! $this->fitsRate($code, TaxCodeDirection::Input, $group['rate'])) {
                $code = $this->codeFor($organization, TaxCodeDirection::Input, $group['rate'], $on);
                $taxAccount = $code?->taxAccount;
                if (! $taxAccount instanceof AccountingAccount) {
                    $blockers[] = (string) __('accounting.inbox.blocker.missing_input_tax_code', ['rate' => $group['rate']]);

                    return [];
                }
            } elseif ($taxRule === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::TaxInput);

                return [];
            } else {
                $ruleVersions[] = $taxRule->versionTag();
            }
        }

        // Ein Kennzeichen an der Aufwandsregel gilt, solange es zum Satz passt.
        $ruleCode = $expense->taxCode;
        $lineCode = $ruleCode instanceof AccountingTaxCode && ! $ruleCode->isSelfAssessed()
            && ($group['rate'] === null || $this->fitsRate($ruleCode, $ruleCode->direction, $group['rate']))
            ? $ruleCode->id
            : $code?->id;

        $lines = [];
        $line = $this->line(PostingAccountRole::Expense, $expense, $group['net'], '0.00', $number)?->withTax($lineCode, $taxed ? $group['tax'] : null);
        if ($line instanceof PostingProposalLine) {
            $lines[] = $line;
        }
        if ($taxed && $taxAccount instanceof AccountingAccount) {
            $lines[] = $this->accountLine(PostingAccountRole::TaxInput, $taxAccount, $group['tax'], '0.00', $number);
        }

        return $lines;
    }

    /**
     * Steuerschuld des Empfängers: Der Beleg ist netto; Umsatzsteuer und
     * Vorsteuer entstehen in gleicher Höhe zum Kennzeichen des Steuerfalls.
     * Der Satz ist der des Belegs, bei 0 % (so verlangt es die E-Rechnung für
     * AE und K) der Regelsatz.
     *
     * @param  array{rate: ?numeric-string, net: numeric-string, tax: numeric-string}  $group
     * @param  list<string>  $blockers
     * @return list<PostingProposalLine>
     */
    private function selfAssessedLines(Organization $organization, IncomingInvoiceTaxCase $case, AccountingPostingRule $expense, array $group, CurrencyCode $currency, CarbonImmutable $on, string $number, array &$blockers): array {
        if (! NumberHelper::isZeroPrecise($group['tax'])) {
            $blockers[] = (string) __('accounting.inbox.blocker.self_assessed_with_tax', ['case' => $case->label()]);

            return [];
        }
        // Kleinunternehmer schulden die Steuer, ziehen aber keine Vorsteuer ab (§ 19 Abs. 1 UStG) — dafür gibt es keinen Buchungsweg.
        if ((string) data_get((array) ($organization->settings ?? []), 'einvoice.small_business', '0') === '1') {
            $blockers[] = (string) __('accounting.inbox.blocker.self_assessed_small_business', ['case' => $case->label()]);

            return [];
        }

        $rate = $group['rate'] !== null && NumberHelper::isPositivePrecise($group['rate'])
            ? $group['rate']
            : Decimal::of($this->taxes->standardRate($this->taxes->sellerCountry($organization)), 2)->getValue();
        $code = AccountingTaxCode::query()
            ->where('organization_id', $organization->id)
            ->where('direction', TaxCodeDirection::SelfAssessed->value)
            ->where('tax_case', $case->value)
            ->where('rate', $rate)
            ->validOn($on)
            ->orderByDesc('valid_from')
            ->with(['taxAccount', 'inputTaxAccount'])
            ->first();
        if (! $code instanceof AccountingTaxCode || ! $code->taxAccount instanceof AccountingAccount || ! $code->inputTaxAccount instanceof AccountingAccount) {
            $blockers[] = (string) __('accounting.inbox.blocker.missing_self_assessed_code', ['case' => $case->label(), 'rate' => $rate]);

            return [];
        }

        $tax = Money::of($group['net'], $currency)->percentage($rate)->getAmount();
        $lines = [];
        $line = $this->line(PostingAccountRole::Expense, $expense, $group['net'], '0.00', $number)?->withTax($code->id, $tax);
        if ($line instanceof PostingProposalLine) {
            $lines[] = $line;
        }
        $lines[] = $this->accountLine(PostingAccountRole::TaxInput, $code->inputTaxAccount, $tax, '0.00', $code->code, 'taxcode:' . $code->id);
        $lines[] = $this->accountLine(PostingAccountRole::TaxOutput, $code->taxAccount, '0.00', $tax, $code->code, 'taxcode:' . $code->id);

        return $lines;
    }

    /** @param numeric-string $rate */
    private function fitsRate(?AccountingTaxCode $code, TaxCodeDirection $direction, string $rate): bool {
        return $code instanceof AccountingTaxCode
            && $code->direction === $direction
            && NumberHelper::comparePrecise((string) $code->rate, $rate, 2) === 0;
    }

    /** @param numeric-string $rate */
    private function codeFor(Organization $organization, TaxCodeDirection $direction, string $rate, CarbonImmutable $on): ?AccountingTaxCode {
        return AccountingTaxCode::query()
            ->where('organization_id', $organization->id)
            ->where('direction', $direction->value)
            ->where('rate', $rate)
            ->whereNotNull('tax_account_id')
            ->validOn($on)
            ->orderByDesc('valid_from')
            ->with('taxAccount')
            ->first();
    }
}
