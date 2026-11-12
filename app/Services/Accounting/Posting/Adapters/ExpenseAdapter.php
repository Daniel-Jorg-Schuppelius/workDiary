<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting\Adapters;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Finance\{PostingAccountRole, PostingSourceKind, TaxCodeDirection};
use App\Models\Accounting\{AccountingAccount, AccountingTaxCode};
use App\Models\Platform\{Organization, User};
use App\Models\Travel\Expense;
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine};
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Freigegebene Auslage → Aufwand und Vorsteuer an Mitarbeiterverbindlichkeit
 * (Feature 125, MVP-673).
 *
 * Die Gegenpartei ist die Person, die ausgelegt hat — deshalb steht sie an
 * der Verbindlichkeitszeile. Ohne diesen Bezug wäre später nicht mehr
 * feststellbar, wem die Organisation das Geld schuldet.
 */
class ExpenseAdapter extends AbstractPostingAdapter {
    public function kind(): PostingSourceKind {
        return PostingSourceKind::Expense;
    }

    /** @return Collection<int, Model> */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        /** @var Collection<int, Model> $expenses */
        $expenses = Expense::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [ExpenseStatus::Approved->value, ExpenseStatus::Reimbursed->value])
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->with(['category', 'user'])
            ->orderBy('date')
            ->get();

        return $expenses;
    }

    public function proposalFor(Organization $organization, Model $source): PostingProposal {
        assert($source instanceof Expense);

        $date = CarbonImmutable::parse($source->date ?? now())->startOfDay();
        $blockers = [];
        $lines = [];
        $ruleVersions = [];

        if ($this->alreadyHandedOver($source)) {
            $blockers[] = (string) __('accounting.inbox.blocker.handed_over');
        }

        $gross = $source->amount_gross?->getAmount() ?? '0.00';
        $net = $source->amount_net?->getAmount() ?? '0.00';
        $tax = $source->tax_amount?->getAmount() ?? '0.00';

        if (NumberHelper::isZeroPrecise($gross)) {
            $blockers[] = (string) __('accounting.inbox.blocker.no_amount');
        }
        if (NumberHelper::isZeroPrecise($net) && NumberHelper::isPositivePrecise($gross)) {
            $net = $gross;
            $tax = '0.00';
        }

        // Die Kategorie ist das fachliche Merkmal der Auslage — an ihr hängt
        // das Aufwandskonto, nicht am Zufall des Belegtexts.
        $context = ['expense_category_id' => (string) ($source->expense_category_id ?? '')];

        // Vorsteuer zum Satz des Belegs (MVP-1623): Ein Kennzeichen der Regel muss zum Satz passen, sonst gilt das
        // Vorsteuerkennzeichen dieses Satzes. Gibt es keines, ist es meist ausländische Umsatzsteuer — keine Vorsteuer.
        // Eine Regel ohne Kennzeichen bucht wie bisher auf ihr Konto.
        $taxed = NumberHelper::isPositivePrecise($tax);
        $rate = $source->tax_rate !== null ? NumberHelper::roundPrecise((string) $source->tax_rate->getNumericValue(), 2) : null;
        $taxRule = $taxed ? $this->rule($organization, PostingAccountRole::TaxInput, $rate !== null ? ['tax_rate' => $rate] : [], $date) : null;
        $taxCode = $taxRule?->taxCode;
        $taxAccount = $taxRule?->account;
        if ($taxed && $rate !== null && $taxCode instanceof AccountingTaxCode && ! $this->fitsRate($taxCode, $rate)) {
            $taxCode = AccountingTaxCode::query()
                ->where('organization_id', $organization->id)
                ->where('direction', TaxCodeDirection::Input->value)
                ->where('rate', $rate)
                ->whereNotNull('tax_account_id')
                ->validOn($date)
                ->orderByDesc('valid_from')
                ->with('taxAccount')
                ->first();
            $taxAccount = $taxCode?->taxAccount;
            if (! $taxAccount instanceof AccountingAccount) {
                $blockers[] = (string) __('accounting.inbox.blocker.expense_rate_without_input_tax', ['rate' => NumberHelper::toGermanFormat((float) $rate, 2)]);
            }
        }

        $expenseRule = $this->rule($organization, PostingAccountRole::Expense, $context, $date)
            ?? $this->rule($organization, PostingAccountRole::Expense, [], $date);
        if ($expenseRule === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Expense, $context);
        } else {
            // Kennzeichen und Vorsteuer stehen an der Aufwandszeile, die Steuerzeile trägt keines; das Kennzeichen der Regel nur, wenn es zum Satz passt.
            $ruleCode = $expenseRule->taxCode;
            $lineCode = $ruleCode instanceof AccountingTaxCode && ($rate === null || $this->fitsRate($ruleCode, $rate)) ? $ruleCode->id : $taxCode?->id;
            $line = $this->line(PostingAccountRole::Expense, $expenseRule, $net, '0.00', (string) $source->description)
                ?->withTax($taxed ? $lineCode : $expenseRule->accounting_tax_code_id, $taxed && $taxAccount instanceof AccountingAccount ? $tax : null);
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $expenseRule->versionTag();
            }
        }

        if ($taxed) {
            if ($taxRule === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::TaxInput);
            } elseif ($taxAccount instanceof AccountingAccount) {
                $lines[] = $this->accountLine(PostingAccountRole::TaxInput, $taxAccount, $tax, '0.00', (string) $source->description, $taxRule->versionTag());
                $ruleVersions[] = $taxRule->versionTag();
            }
        }

        $payable = $this->rule($organization, PostingAccountRole::EmployeePayable, [], $date);
        if ($payable === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::EmployeePayable);
        } else {
            $line = $this->line(
                PostingAccountRole::EmployeePayable,
                $payable,
                '0.00',
                $gross,
                (string) $source->description,
                MorphMap::alias(User::class),
                $source->user_id,
            );
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $payable->versionTag();
            }
        }

        // Fremdwährung zum Monatskurs (MVP-1012); ohne Kurs bleibt der Beleg in der Inbox.
        $fx = $this->inBaseCurrency($organization, $source->currency, $date, $lines);
        if ($fx['blocker'] !== null) {
            $blockers[] = $fx['blocker'];
        }

        return new PostingProposal(
            kind: $this->kind(),
            source: $source,
            sourceKey: $this->sourceKey($source),
            bookedOn: $date,
            memo: (string) __('accounting.inbox.memo.expense', [
                'description' => (string) ($source->description ?? '—'),
                'user' => (string) ($source->user instanceof User ? $source->user->name : '—'),
            ]),
            lines: $fx['lines'],
            blockers: array_values(array_unique($blockers)),
            documentOn: $date,
            documentReference: $source->vendor !== null ? (string) $source->vendor : null,
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: (string) ($source->description ?? '—'),
            extra: $fx['extra'],
        );
    }

    /** @param numeric-string $rate */
    private function fitsRate(?AccountingTaxCode $code, string $rate): bool {
        return $code instanceof AccountingTaxCode
            && $code->direction === TaxCodeDirection::Input
            && NumberHelper::comparePrecise((string) $code->rate, $rate, 2) === 0;
    }
}
