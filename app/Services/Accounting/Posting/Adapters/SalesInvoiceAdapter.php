<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesInvoiceAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting\Adapters;

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind};
use App\Models\{Customer, Invoice, Organization};
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Ausgangsrechnung → Forderung an Erlös und Umsatzsteuer (Feature 125, MVP-673).
 *
 * Die Steueraufteilung kommt aus dem eingefrorenen `tax_breakdown` des Belegs:
 * je Steuersatz eine Erlös- und eine Steuerzeile. Der Adapter rechnet nichts
 * nach — täte er es, gäbe es zwei Wahrheiten über denselben Beleg.
 */
class SalesInvoiceAdapter extends AbstractPostingAdapter {
    public function kind(): PostingSourceKind {
        return PostingSourceKind::SalesInvoice;
    }

    /** @return Collection<int, Model> */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        /** @var Collection<int, Model> $invoices */
        $invoices = Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID])
            ->whereNotNull('issued_on')
            ->whereDate('issued_on', '>=', $from->toDateString())
            ->whereDate('issued_on', '<=', $to->toDateString())
            ->with('customer')
            ->orderBy('issued_on')
            ->get();

        return $invoices;
    }

    public function proposalFor(Organization $organization, Model $source): PostingProposal {
        assert($source instanceof Invoice);

        $issuedOn = CarbonImmutable::parse($source->issued_on ?? now())->startOfDay();
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

        $breakdown = is_array($source->tax_breakdown) ? $source->tax_breakdown : [];
        if ($breakdown === []) {
            $blockers[] = (string) __('accounting.inbox.blocker.no_tax_breakdown');
        }

        $receivable = $this->rule($organization, PostingAccountRole::Receivable, [], $issuedOn);
        if ($receivable === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Receivable);
        } else {
            $line = $this->line(
                PostingAccountRole::Receivable,
                $receivable,
                $source->total?->getAmount() ?? '0.00',
                '0.00',
                $source->number,
                Customer::class,
                $source->customer_id,
            );
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $receivable->versionTag();
            }
        }

        foreach ($breakdown as $group) {
            $rate = (string) ($group['rate'] ?? '0.00');
            // Die Aufschlüsselung liegt als JSON vor; Money normalisiert die
            // Rohwerte ohne Float-Zwischenschritt (Vollscan 2026-08-23, C1).
            $net = Money::of((string) ($group['net'] ?? '0'), $source->currencyCode())->getAmount();
            $tax = Money::of((string) ($group['tax'] ?? '0'), $source->currencyCode())->getAmount();
            $context = ['tax_rate' => $rate];

            $revenue = $this->rule($organization, PostingAccountRole::Revenue, $context, $issuedOn);
            if ($revenue === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Revenue, $context);
            } else {
                $line = $this->line(PostingAccountRole::Revenue, $revenue, '0.00', $net, $source->number);
                if ($line instanceof PostingProposalLine) {
                    $lines[] = $line;
                    $ruleVersions[] = $revenue->versionTag();
                }
            }

            // Steuerzeile nur, wenn der Beleg auch Steuer ausweist.
            if (NumberHelper::isZeroPrecise($tax)) {
                continue;
            }

            $taxRule = $this->rule($organization, PostingAccountRole::TaxOutput, $context, $issuedOn);
            if ($taxRule === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::TaxOutput, $context);

                continue;
            }

            $line = $this->line(PostingAccountRole::TaxOutput, $taxRule, '0.00', $tax, $source->number, taxAmount: $tax);
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $taxRule->versionTag();
            }
        }

        return new PostingProposal(
            kind: $this->kind(),
            source: $source,
            sourceKey: $this->sourceKey($source),
            bookedOn: $issuedOn,
            memo: (string) __('accounting.inbox.memo.sales_invoice', [
                'number' => (string) $source->number,
                // Über die ID statt die Relation: eine Rechnung ohne Kunde ist
                // Altbestand, kein Grund für einen leeren Buchungstext.
                'customer' => (string) (Customer::query()->whereKey($source->customer_id)->value('name') ?? '—'),
            ]),
            lines: $lines,
            blockers: array_values(array_unique($blockers)),
            documentOn: $issuedOn,
            documentReference: (string) $source->number,
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: (string) $source->number,
            // Ohne Fälligkeit hätte der offene Posten keine Altersstruktur.
            extra: ['due_date' => $source->due_on?->toDateString()],
        );
    }
}
