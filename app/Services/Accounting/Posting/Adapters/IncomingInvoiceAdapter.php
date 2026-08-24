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

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind};
use App\Models\{IncomingEInvoice, Organization};
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Eingangsrechnung → Aufwand und Vorsteuer an Verbindlichkeit
 * (Feature 125, MVP-673).
 *
 * Buchungsfähig erst nach **fachlicher Freigabe**: Ein nur eingegangener Beleg
 * ist eine Behauptung des Lieferanten, keine Verbindlichkeit.
 */
class IncomingInvoiceAdapter extends AbstractPostingAdapter {
    public function kind(): PostingSourceKind {
        return PostingSourceKind::IncomingInvoice;
    }

    /** @return Collection<int, Model> */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        /** @var Collection<int, Model> $invoices */
        $invoices = IncomingEInvoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [IncomingEInvoice::STATUS_APPROVED, IncomingEInvoice::STATUS_PAYMENT_RELEASED])
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

        $foreign = $this->foreignCurrencyBlocker($organization, $source->currency);
        if ($foreign !== null) {
            $blockers[] = $foreign;
        }

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

        $context = ['seller' => (string) ($source->seller_name ?? '')];

        $expense = $this->rule($organization, PostingAccountRole::Expense, $context, $issuedOn)
            ?? $this->rule($organization, PostingAccountRole::Expense, [], $issuedOn);
        if ($expense === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Expense);
        } else {
            $line = $this->line(PostingAccountRole::Expense, $expense, $net, '0.00', (string) $source->invoice_number);
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $expense->versionTag();
            }
        }

        if (NumberHelper::isPositivePrecise($tax)) {
            $taxRule = $this->rule($organization, PostingAccountRole::TaxInput, [], $issuedOn);
            if ($taxRule === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::TaxInput);
            } else {
                $line = $this->line(PostingAccountRole::TaxInput, $taxRule, $tax, '0.00', (string) $source->invoice_number, taxAmount: $tax);
                if ($line instanceof PostingProposalLine) {
                    $lines[] = $line;
                    $ruleVersions[] = $taxRule->versionTag();
                }
            }
        }

        $payable = $this->rule($organization, PostingAccountRole::Payable, [], $issuedOn);
        if ($payable === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Payable);
        } else {
            $line = $this->line(PostingAccountRole::Payable, $payable, '0.00', $gross, (string) $source->invoice_number);
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $payable->versionTag();
            }
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
            lines: $lines,
            blockers: array_values(array_unique($blockers)),
            documentOn: $issuedOn,
            documentReference: (string) ($source->invoice_number ?? null),
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: (string) ($source->invoice_number ?? $source->seller_name ?? '—'),
            extra: ['due_date' => $source->due_date?->toDateString()],
        );
    }
}
