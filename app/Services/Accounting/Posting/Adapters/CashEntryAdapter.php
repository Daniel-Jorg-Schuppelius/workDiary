<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashEntryAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting\Adapters;

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind};
use App\Models\{CashEntry, Organization};
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Kassenbucheintrag → Kasse an Gegenkonto (Einnahme) bzw. Gegenkonto an Kasse
 * (Ausgabe), Feature 125, MVP-673.
 *
 * Das Gegenkonto hängt an der Kasse und der Richtung; ohne passende Regel
 * bleibt der Eintrag blockiert. Ein Klärungskonto wird hier bewusst nicht
 * automatisch gezogen — die Kasse ist der Ort, an dem geraten am teuersten ist.
 */
class CashEntryAdapter extends AbstractPostingAdapter {
    public function kind(): PostingSourceKind {
        return PostingSourceKind::CashEntry;
    }

    /** @return Collection<int, Model> */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        /** @var Collection<int, Model> $entries */
        $entries = CashEntry::query()
            ->where('organization_id', $organization->id)
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString())
            ->with('register')
            ->orderBy('booked_on')
            ->get();

        return $entries;
    }

    public function proposalFor(Organization $organization, Model $source): PostingProposal {
        assert($source instanceof CashEntry);

        $bookedOn = CarbonImmutable::parse($source->booked_on)->startOfDay();
        $blockers = [];
        $lines = [];
        $ruleVersions = [];

        // Die Währung führt die Kasse, nicht der einzelne Eintrag.
        $foreign = $this->foreignCurrencyBlocker($organization, $source->register?->currency);
        if ($foreign !== null) {
            $blockers[] = $foreign;
        }

        if ($this->alreadyHandedOver($source)) {
            $blockers[] = (string) __('accounting.inbox.blocker.handed_over');
        }

        $amount = $this->money($source->amount);
        if ((float) $amount === 0.0) {
            $blockers[] = (string) __('accounting.inbox.blocker.no_amount');
        }

        $isIncome = $source->direction === CashEntry::DIRECTION_IN;
        $context = [
            'cash_register_id' => (string) $source->cash_register_id,
            'direction' => (string) $source->direction,
        ];

        $cashRule = $this->rule($organization, PostingAccountRole::Cash, $context, $bookedOn)
            ?? $this->rule($organization, PostingAccountRole::Cash, ['cash_register_id' => (string) $source->cash_register_id], $bookedOn)
            ?? $this->rule($organization, PostingAccountRole::Cash, [], $bookedOn);
        if ($cashRule === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Cash, $context);
        } else {
            $line = $this->line(
                PostingAccountRole::Cash,
                $cashRule,
                $isIncome ? $amount : '0.00',
                $isIncome ? '0.00' : $amount,
                (string) $source->purpose,
            );
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $cashRule->versionTag();
            }
        }

        // Gegenkonto: bei Einnahme Erlös, bei Ausgabe Aufwand.
        $counterRole = $isIncome ? PostingAccountRole::Revenue : PostingAccountRole::Expense;
        $counterRule = $this->rule($organization, $counterRole, $context, $bookedOn)
            ?? $this->rule($organization, $counterRole, [], $bookedOn);
        if ($counterRule === null) {
            $blockers[] = $this->missingRuleBlocker($counterRole, $context);
        } else {
            $line = $this->line(
                $counterRole,
                $counterRule,
                $isIncome ? '0.00' : $amount,
                $isIncome ? $amount : '0.00',
                (string) $source->purpose,
            );
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $counterRule->versionTag();
            }
        }

        return new PostingProposal(
            kind: $this->kind(),
            source: $source,
            sourceKey: $this->sourceKey($source),
            bookedOn: $bookedOn,
            memo: (string) __('accounting.inbox.memo.cash_entry', [
                'purpose' => (string) $source->purpose,
                'register' => (string) ($source->register instanceof \App\Models\CashRegister ? $source->register->name : '—'),
            ]),
            lines: $lines,
            blockers: array_values(array_unique($blockers)),
            documentOn: $bookedOn,
            documentReference: 'KB-' . $source->seq_no,
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: (string) $source->purpose,
        );
    }
}
