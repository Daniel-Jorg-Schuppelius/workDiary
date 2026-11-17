<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderReviewChain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Review;

use App\Enums\User\Permission;
use App\Models\Approval\Approval;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Approval\ApprovalService;

/**
 * Freigabestufe der internen Prüfung eines Auftrags (Feature 198, MVP-1636):
 * eine Stufe je Vorlage im Genehmigungs-Eingang. Zuständig ist die benannte
 * prüfende Person, sonst jede Person mit `order.review`. Führend bleibt der
 * Status des Auftrags; die Stufe hält Zuständigkeit und Entscheidung für den
 * Eingang fest. Nach einer Rückfrage bleibt die Stufe offen und erscheint mit
 * der nächsten Vorlage wieder; wechselt die prüfende Person, beginnt eine
 * neue Runde.
 */
final class OrderReviewChain {
    public function __construct(private readonly ApprovalService $approvals) {}

    /** @return array{type: string, value: int|string} Zuständigkeit der Stufe */
    public function rule(DiaryEntry $entry): array {
        return $entry->reviewer_user_id !== null
            ? ['type' => 'user', 'value' => (int) $entry->reviewer_user_id]
            : ['type' => 'permission', 'value' => Permission::OrderReview->value];
    }

    /** Offene Stufe für die aktuelle Vorlage anlegen oder die offene weiterverwenden. */
    public function open(DiaryEntry $entry): void {
        $rule = $this->rule($entry);
        $step = $this->openStep($entry);
        if ($step instanceof Approval && $this->sameRule((array) $step->approver_rule, $rule)) {
            return;
        }

        if ($this->approvals->currentRound($entry) === 0) {
            $this->approvals->createChain($entry, [$rule]);

            return;
        }

        $round = $this->approvals->startNextRound($entry);
        Approval::query()
            ->where('approvable_type', $entry->getMorphClass())
            ->where('approvable_id', $entry->getKey())
            ->where('round', $round)
            ->get()
            ->each(static fn (Approval $fresh) => $fresh->update(['approver_rule' => $rule]));
    }

    /**
     * Entscheidung der prüfenden Person an der offenen Stufe vermerken
     * (`approved` oder `question`); ohne offene Stufe nichts.
     */
    public function decide(DiaryEntry $entry, User $actor, string $decision, ?string $reason): void {
        $step = $this->openStep($entry);
        if (! $step instanceof Approval) {
            return;
        }

        $this->approvals->decide(
            $step,
            $actor,
            $decision,
            $reason,
            $entry->review_submitter_user_id !== null ? (int) $entry->review_submitter_user_id : null,
        );
    }

    /** Offene Stufe der geltenden Runde (auch nach einer Rückfrage), jüngste zuerst. */
    public function openStep(DiaryEntry $entry): ?Approval {
        $round = $this->approvals->currentRound($entry);
        if ($round === 0) {
            return null;
        }

        return Approval::query()
            ->where('approvable_type', $entry->getMorphClass())
            ->where('approvable_id', $entry->getKey())
            ->where('round', $round)
            ->where(static fn ($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<mixed>  $stored
     * @param  array{type: string, value: int|string}  $rule
     */
    private function sameRule(array $stored, array $rule): bool {
        return (string) ($stored['type'] ?? '') === $rule['type'] && (string) ($stored['value'] ?? '') === (string) $rule['value'];
    }
}
