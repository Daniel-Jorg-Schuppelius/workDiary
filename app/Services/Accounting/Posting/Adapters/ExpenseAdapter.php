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
use App\Enums\Finance\{PostingAccountRole, PostingSourceKind};
use App\Models\{Expense, Organization, User};
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine};
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

        $foreign = $this->foreignCurrencyBlocker($organization, $source->currency);
        if ($foreign !== null) {
            $blockers[] = $foreign;
        }

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

        $expenseRule = $this->rule($organization, PostingAccountRole::Expense, $context, $date)
            ?? $this->rule($organization, PostingAccountRole::Expense, [], $date);
        if ($expenseRule === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Expense, $context);
        } else {
            $line = $this->line(PostingAccountRole::Expense, $expenseRule, $net, '0.00', (string) $source->description);
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $expenseRule->versionTag();
            }
        }

        if (NumberHelper::isPositivePrecise($tax)) {
            $taxRule = $this->rule($organization, PostingAccountRole::TaxInput, [], $date);
            if ($taxRule === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::TaxInput);
            } else {
                $line = $this->line(PostingAccountRole::TaxInput, $taxRule, $tax, '0.00', (string) $source->description, taxAmount: $tax);
                if ($line instanceof PostingProposalLine) {
                    $lines[] = $line;
                    $ruleVersions[] = $taxRule->versionTag();
                }
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
                User::class,
                $source->user_id,
            );
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $payable->versionTag();
            }
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
            lines: $lines,
            blockers: array_values(array_unique($blockers)),
            documentOn: $date,
            documentReference: $source->vendor !== null ? (string) $source->vendor : null,
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: (string) ($source->description ?? '—'),
        );
    }
}
