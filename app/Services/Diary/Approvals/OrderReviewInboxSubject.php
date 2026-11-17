<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderReviewInboxSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Approvals;

use App\Enums\Diary\Status;
use App\Models\Approval\Approval;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Approval\Contracts\ApprovalInboxSubject;
use App\Services\Approval\Dto\ApprovalInboxEntry;
use App\Services\Diary\OrderService;
use App\Services\Diary\Review\OrderReviewChain;
use App\Support\CarbonFmt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Gate};
use InvalidArgumentException;

/**
 * Aufträge zur internen Prüfung im Genehmigungs-Eingang (Feature 198,
 * MVP-1636). Entschieden wird auf dem Weg des Auftrags: Genehmigen gibt frei,
 * Ablehnen und Rückfrage stellen eine Rückfrage (Text Pflicht), Weitergeben
 * benennt die prüfende Person. Eigene Vorlagen erscheinen nie
 * ({@see requesterId()}).
 */
final class OrderReviewInboxSubject implements ApprovalInboxSubject {
    public function __construct(private readonly OrderService $orders) {}

    public function approvableClass(): string {
        return DiaryEntry::class;
    }

    public function eagerLoad(): array {
        return ['customer:id,name', 'entryType:id,label', 'reviewSubmitter:id,name'];
    }

    public function awaitsDecision(Model $approvable): bool {
        return $approvable instanceof DiaryEntry && $approvable->status === Status::InReview;
    }

    public function requesterId(Model $approvable): ?int {
        return $approvable instanceof DiaryEntry && $approvable->review_submitter_user_id !== null ? (int) $approvable->review_submitter_user_id : null;
    }

    public function present(Model $approvable, User $viewer): ApprovalInboxEntry {
        if (! $approvable instanceof DiaryEntry) {
            return new ApprovalInboxEntry('—');
        }

        return new ApprovalInboxEntry(
            self::title($approvable),
            Gate::forUser($viewer)->allows('view', $approvable) ? route('diary.show', $approvable) : null,
            (string) __('diary.review.inbox_reference', [
                'name' => (string) ($approvable->reviewSubmitter->name ?? '—'),
                'date' => $approvable->review_submitted_at !== null ? CarbonFmt::fdate($approvable->review_submitted_at) : '—',
            ]),
            (string) __('diary.review.inbox_type'),
        );
    }

    public function decide(Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): void {
        $entry = DiaryEntry::query()->whereKey($approval->approvable_id)->firstOrFail();

        if ($decision === 'approved') {
            Gate::forUser($actor)->authorize('approveReview', $entry);
            $this->orders->approveReview($entry, $actor, $reason);
        } elseif ($decision === 'rejected' || $decision === 'question') {
            Gate::forUser($actor)->authorize('queryReview', $entry);
            $this->orders->queryReview($entry, $actor, (string) $reason);
        } elseif ($decision === 'delegated') {
            $this->delegate($entry, $actor, $delegateUserId);
        } else {
            throw new InvalidArgumentException('Unbekannte Entscheidung.');
        }
    }

    /** Titel einer Prüfvorlage: Titel, sonst Eintragsart und Periode. */
    public static function title(DiaryEntry $entry): string {
        $title = trim((string) $entry->title);
        if ($title !== '') {
            return $title;
        }

        return trim(implode(' ', array_filter([$entry->entryType->label ?? null, $entry->period_key?->label(), $entry->customer->name ?? null]))) ?: '#' . $entry->id;
    }

    /**
     * Weitergeben heißt: die Empfängerin wird prüfende Person des Auftrags. Die
     * Stufe wechselt dafür in eine neue Runde mit ihr als Zuständiger.
     */
    private function delegate(DiaryEntry $entry, User $actor, ?int $delegateUserId): void {
        Gate::forUser($actor)->authorize('approveReview', $entry);
        $delegate = $delegateUserId !== null
            ? User::query()->whereKey($delegateUserId)->where('organization_id', $entry->organization_id)->first()
            : null;
        if (! $delegate instanceof User) {
            throw new InvalidArgumentException((string) __('Delegation braucht einen Empfänger der eigenen Organisation.'));
        }
        if ((int) $delegate->id === (int) $entry->review_submitter_user_id) {
            throw new \RuntimeException((string) __('diary.review.error.own_submission'));
        }

        DB::transaction(function () use ($entry, $actor, $delegate): void {
            /** @var DiaryEntry $locked */
            $locked = DiaryEntry::query()->lockForUpdate()->findOrFail($entry->id);
            if ($locked->status !== Status::InReview) {
                throw new \RuntimeException((string) __('diary.review.error.not_in_review'));
            }
            $locked->forceFill(['reviewer_user_id' => $delegate->id])->save();
            $locked->record('order.reviewerChanged', ['reviewer_user_id' => $delegate->id], $actor, extra: [
                'from_status' => $locked->status->slug(),
                'to_status' => $locked->status->slug(),
                'actor_kind' => 'user',
            ]);
            app(OrderReviewChain::class)->open($locked);
        });
    }
}
