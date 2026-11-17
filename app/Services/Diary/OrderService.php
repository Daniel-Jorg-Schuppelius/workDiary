<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Diary;

use App\Enums\Classification\ClassificationRequirementPhase;
use App\Enums\Diary\Status;
use App\Enums\Protocol\{ProtocolSignatureRole, ProtocolStatus};
use App\Events\Diary\OrderCancelled;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use App\Services\Classification\ClassificationRequirementValidator;
use App\Services\Diary\Review\OrderReviewChain;
use App\Services\Timesheet\Stopwatch;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService {
    /** Aktionen der prüfenden Person (Feature 198): nie durch die Person, die vorgelegt hat. */
    private const array REVIEW_DECISIONS = ['approveReview', 'queryReview', 'approveAndComplete'];

    public function __construct(
        private readonly ClassificationRequirementValidator $requirements,
        private readonly Stopwatch $stopwatch,
        private readonly OrderReviewChain $reviewChain,
    ) {}

    public function accept(DiaryEntry $entry, User $actor): DiaryEntry {
        return $this->transition($entry, $actor, 'accept', Status::Accepted, [
            'accepted_at' => CarbonImmutable::now(),
            'accepted_by_user_id' => $actor->id,
        ]);
    }

    /** Annahme zurückgeben: der Auftrag ist wieder geplant und offen für eine andere Person. */
    public function reject(DiaryEntry $entry, User $actor, string $reason): DiaryEntry {
        return $this->transition($entry, $actor, 'reject', Status::Planned, [
            'accepted_at' => null,
            'accepted_by_user_id' => null,
        ], $reason);
    }

    public function start(DiaryEntry $entry, User $actor): DiaryEntry {
        return $this->transition($entry, $actor, 'start', Status::InProgress, [
            'started_at' => $entry->started_at ?? CarbonImmutable::now(),
        ]);
    }

    public function pause(DiaryEntry $entry, User $actor, string $reason, ?string $note = null): DiaryEntry {
        if (! in_array($reason, ['customer', 'material'], true)) {
            throw new InvalidArgumentException((string) __('Ungültiger Pausengrund.'));
        }

        $target = $reason === 'customer' ? Status::WaitingCustomer : Status::WaitingMaterial;

        return $this->transition($entry, $actor, 'pause', $target, [
            'paused_at' => CarbonImmutable::now(),
            'pause_reason' => $reason,
            'pause_note' => $note,
        ], $note, ['reason' => $reason]);
    }

    public function resume(DiaryEntry $entry, User $actor): DiaryEntry {
        $now = CarbonImmutable::now();
        $waitSeconds = $entry->paused_at
            ? max(0, $entry->paused_at->diffInSeconds($now))
            : 0;

        return $this->transition($entry, $actor, 'resume', Status::InProgress, [
            'resumed_at' => $now,
            'wait_seconds_total' => (int) $entry->wait_seconds_total + $waitSeconds,
            // Grund und Notiz der Pause stehen im Journal (order.pause).
            'paused_at' => null,
            'pause_reason' => null,
            'pause_note' => null,
        ], payload: ['wait_seconds' => $waitSeconds]);
    }

    public function complete(DiaryEntry $entry, User $actor, string $summary): DiaryEntry {
        $completed = $this->transition($entry, $actor, 'complete', Status::Completed, [
            'completed_at' => CarbonImmutable::now(),
            'completed_by_user_id' => $actor->id,
            'completion_summary' => $summary,
        ], $summary);
        \App\Events\Diary\OrderCompleted::dispatch((int) $completed->organization_id, (int) $completed->id);

        return $completed;
    }

    /**
     * Zur Prüfung vorlegen (Feature 198, MVP-1635): nur Aufträge mit
     * Prüfpflicht, aus „In Bearbeitung“. Die Vorlage erscheint im
     * Genehmigungs-Eingang der prüfenden Personen.
     */
    public function submitForReview(DiaryEntry $entry, User $actor, ?string $note = null): DiaryEntry {
        return DB::transaction(function () use ($entry, $actor, $note): DiaryEntry {
            $submitted = $this->transition($entry, $actor, 'submitReview', Status::InReview, [
                'review_submitted_at' => CarbonImmutable::now(),
                'review_submitter_user_id' => $actor->id,
            ], StringHelper::trimToNull($note));
            $this->reviewChain->open($submitted);

            return $submitted;
        });
    }

    /**
     * Arbeit fertig außerhalb des Auftragsdialogs (Fahrt, Rundgang, Tourstopp):
     * ein prüfpflichtiger Auftrag wird vorgelegt statt erledigt (Feature 198).
     * Ein geplanter, angenommener oder wartender Auftrag läuft dafür über
     * Annehmen, Beginnen bzw. Fortsetzen; steht er schon in der Prüfung, ist er
     * abgeschlossen oder storniert, bleibt er, wie er ist. Aufträge ohne
     * Prüfpflicht schließt der Aufrufer weiter selbst ab.
     */
    public function submitFinishedWork(DiaryEntry $entry, User $actor, ?string $note = null): DiaryEntry {
        return DB::transaction(function () use ($entry, $actor, $note): DiaryEntry {
            $entry = $this->startFromTimeEntry($entry, $actor);

            return $entry->status === Status::InProgress && (bool) $entry->requires_review
                ? $this->submitForReview($entry, $actor, $note)
                : $entry;
        });
    }

    /** Vorlage zurückziehen: der Auftrag ist wieder in Bearbeitung. */
    public function withdrawReview(DiaryEntry $entry, User $actor): DiaryEntry {
        return $this->transition($entry, $actor, 'withdrawReview', Status::InProgress, []);
    }

    /** Freigeben: die Bearbeitung schließt danach ab (Übermittlung, Versand). */
    public function approveReview(DiaryEntry $entry, User $actor, ?string $note = null): DiaryEntry {
        $note = StringHelper::trimToNull($note);

        return DB::transaction(function () use ($entry, $actor, $note): DiaryEntry {
            $approved = $this->transition($entry, $actor, 'approveReview', Status::ReviewApproved, [], $note);
            $this->reviewChain->decide($approved, $actor, 'approved', $note);

            return $approved;
        });
    }

    /** Rückfrage mit Pflichttext: der Ball liegt wieder bei der Bearbeitung. */
    public function queryReview(DiaryEntry $entry, User $actor, string $question): DiaryEntry {
        $question = StringHelper::trimToNull($question);
        if ($question === null) {
            throw new InvalidArgumentException((string) __('diary.review.error.question_required'));
        }

        return DB::transaction(function () use ($entry, $actor, $question): DiaryEntry {
            $queried = $this->transition($entry, $actor, 'queryReview', Status::ReviewQueried, [], $question);
            $this->reviewChain->decide($queried, $actor, 'question', $question);

            return $queried;
        });
    }

    /** Nach einer Rückfrage erneut vorlegen. */
    public function resubmitForReview(DiaryEntry $entry, User $actor, ?string $note = null): DiaryEntry {
        return DB::transaction(function () use ($entry, $actor, $note): DiaryEntry {
            $submitted = $this->transition($entry, $actor, 'resubmitReview', Status::InReview, [
                'review_submitted_at' => CarbonImmutable::now(),
                'review_submitter_user_id' => $actor->id,
            ], StringHelper::trimToNull($note));
            $this->reviewChain->open($submitted);

            return $submitted;
        });
    }

    /** Wo die Freigabe selbst der Abschluss ist: freigeben und abschließen in einem Schritt. */
    public function approveAndComplete(DiaryEntry $entry, User $actor, string $summary): DiaryEntry {
        return DB::transaction(function () use ($entry, $actor, $summary): DiaryEntry {
            $approved = $this->transition($entry, $actor, 'approveAndComplete', Status::ReviewApproved, [], null);
            $this->reviewChain->decide($approved, $actor, 'approved', null);

            return $this->complete($approved, $actor, $summary);
        });
    }

    /**
     * Prüfpflicht am einzelnen Auftrag ab- oder wieder einschalten (eigenes
     * Recht, auditiert). Nur vor der Vorlage: während der Prüfung und nach dem
     * Abschluss bleibt sie, wie sie ist.
     */
    public function setReviewRequirement(DiaryEntry $entry, User $actor, bool $required, ?string $reason = null): DiaryEntry {
        return DB::transaction(function () use ($entry, $actor, $required, $reason): DiaryEntry {
            /** @var DiaryEntry $locked */
            $locked = DiaryEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $status = $locked->status;
            if (! in_array($status, [Status::Planned, Status::Accepted, Status::InProgress, Status::WaitingCustomer, Status::WaitingMaterial], true)) {
                throw InvalidOrderTransitionException::reviewRequirementLocked($status);
            }
            if ((bool) $locked->requires_review === $required) {
                return $locked;
            }

            $locked->forceFill(['requires_review' => $required])->save();
            $locked->record($required ? 'order.reviewRequired' : 'order.reviewWaived', [], $actor, extra: [
                'from_status' => $status->slug(),
                'to_status' => $status->slug(),
                'actor_kind' => 'user',
                'note' => StringHelper::trimToNull($reason),
            ]);

            return $locked->refresh();
        });
    }

    /**
     * @param  string|null  $signerName  Name der abnehmenden Person beim Kunden, falls das Protokoll keine Kundenunterschrift trägt
     */
    public function handover(DiaryEntry $entry, User $actor, Protocol $protocol, ?string $signerName = null): DiaryEntry {
        if (
            $protocol->status !== ProtocolStatus::Signed
            || ! MorphMap::is($protocol->subject_type, DiaryEntry::class)
            || (int) $protocol->subject_id !== (int) $entry->id
        ) {
            throw new InvalidArgumentException((string) __('Für die Abnahme ist ein signiertes Protokoll dieses Auftrags erforderlich.'));
        }

        $signer = self::customerSignerName($protocol) ?? StringHelper::trimToNull($signerName);

        return $this->transition($entry, $actor, 'handover', Status::AcceptedFinal, [
            'accepted_final_at' => $protocol->signed_at ?? CarbonImmutable::now(),
            'accepted_final_by' => $signer !== null ? mb_substr($signer, 0, 120) : null,
            'protocol_id' => $protocol->id,
        ], payload: array_filter(['protocol_id' => $protocol->id, 'signer' => $signer]));
    }

    /** Name unter der jüngsten Kundenunterschrift des Protokolls. */
    public static function customerSignerName(Protocol $protocol): ?string {
        $name = $protocol->signatures()
            ->where('role', ProtocolSignatureRole::Customer->value)
            ->latest('signed_at')
            ->latest('id')
            ->value('signer_name');

        return StringHelper::trimToNull(is_string($name) ? $name : null);
    }

    /**
     * @param  User|null  $actor  null, wenn ein Beleg den Auftrag berechnet (Rechnung, Übergabe)
     * @param  array<string, mixed>  $payload  Herkunft des Belegs
     */
    public function markInvoiced(DiaryEntry $entry, ?User $actor, string $reference, array $payload = []): DiaryEntry {
        return $this->transition($entry, $actor, 'markInvoiced', Status::Invoiced, [
            'invoiced_at' => CarbonImmutable::now(),
            'invoice_reference' => mb_substr($reference, 0, 120),
        ], $reference, $payload);
    }

    /** Der Beleg, der den Auftrag berechnet hat, ist storniert: zurück auf „Abgenommen“. */
    public function revertInvoiced(DiaryEntry $entry, ?User $actor, string $reference): DiaryEntry {
        return $this->transition($entry, $actor, 'revertInvoiced', Status::AcceptedFinal, [
            'invoiced_at' => null,
            'invoice_reference' => null,
        ], $reference);
    }

    public function cancel(DiaryEntry $entry, User $actor, string $reason): DiaryEntry {
        return $this->transition($entry, $actor, 'cancel', Status::Cancelled, [
            'cancelled_at' => CarbonImmutable::now(),
            'cancelled_by_user_id' => $actor->id,
            'cancellation_reason' => $reason,
        ], $reason);
    }

    /**
     * Zeitbuchungen setzen einen zugeordneten Auftrag automatisch in Arbeit.
     */
    public function startFromTimeEntry(DiaryEntry $entry, User $actor): DiaryEntry {
        if ($entry->status === Status::Planned) {
            $entry = $this->accept($entry, $actor);
        }
        if ($entry->status === Status::Accepted) {
            return $this->start($entry, $actor);
        }
        if (in_array($entry->status, [Status::WaitingCustomer, Status::WaitingMaterial], true)) {
            return $this->resume($entry, $actor);
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $payload
     */
    private function transition(
        DiaryEntry $entry,
        ?User $actor,
        string $action,
        Status $target,
        array $attributes,
        ?string $note = null,
        array $payload = [],
    ): DiaryEntry {
        return DB::transaction(function () use ($entry, $actor, $action, $target, $attributes, $note, $payload): DiaryEntry {
            /** @var DiaryEntry $locked */
            $locked = DiaryEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $from = $locked->status;

            if (! in_array($action, $from->allowedActions((bool) $locked->requires_review), true)) {
                throw InvalidOrderTransitionException::forAction($from, $this->actionLabel($action));
            }
            // Vier Augen (Feature 198): wer vorgelegt hat, gibt nicht frei und fragt nicht zurück.
            if (in_array($action, self::REVIEW_DECISIONS, true)
                && $actor !== null
                && (int) $locked->review_submitter_user_id === (int) $actor->id) {
                throw InvalidOrderTransitionException::ownReview();
            }

            // Pflichtklassifikationen vor dem Abschluss (MVP-795): ein
            // unvollständig klassifizierter Auftrag wird nicht fertig.
            if ($action === 'complete') {
                $this->requirements->assertSatisfied($locked, ClassificationRequirementPhase::BeforeComplete);
                // Abschluss setzt beendete Zeiten voraus; wer noch läuft, nennt die Meldung.
                $running = $this->stopwatch->runningOnOrder((int) $locked->id);
                if ($running->isNotEmpty()) {
                    throw InvalidOrderTransitionException::stopwatchRunning(
                        array_values($running->map(fn ($time): string => (string) ($time->user->name ?? '—'))->unique()->all()),
                    );
                }
            }
            // Auf einem wartenden oder stornierten Auftrag läuft keine Zeit weiter.
            if (in_array($action, ['pause', 'cancel'], true)) {
                $stopped = $this->stopwatch->stopOnOrder((int) $locked->id)->modelKeys();
                if ($stopped !== []) {
                    $payload['stopped_time_entry_ids'] = $stopped;
                }
            }

            $locked->forceFill($attributes + ['status' => $target->value])->save();

            $locked->record('order.' . $action, $payload, $actor, extra: [
                'from_status' => $from->slug(),
                'to_status' => $target->slug(),
                'actor_kind' => $actor !== null ? 'user' : 'system',
                'note' => $note,
            ]);

            if ($action === 'cancel') {
                OrderCancelled::dispatch((int) $locked->organization_id, (int) $locked->id, $actor?->id, (string) $note);
            }

            return $locked->refresh();
        });
    }

    private function actionLabel(string $action): string {
        return match ($action) {
            'accept' => (string) __('Annehmen'),
            'reject' => (string) __('diary.lifecycle.action.reject'),
            'revertInvoiced' => (string) __('diary.lifecycle.action.revert_invoiced'),
            'start' => (string) __('Beginnen'),
            'pause' => (string) __('Pausieren'),
            'resume' => (string) __('Fortsetzen'),
            'complete' => (string) __('Abschließen'),
            'handover' => (string) __('Abnahme starten'),
            'markInvoiced' => (string) __('Als berechnet markieren'),
            'cancel' => (string) __('Stornieren'),
            'submitReview' => (string) __('diary.lifecycle.action.submit_review'),
            'withdrawReview' => (string) __('diary.lifecycle.action.withdraw_review'),
            'approveReview' => (string) __('diary.lifecycle.action.approve_review'),
            'queryReview' => (string) __('diary.lifecycle.action.query_review'),
            'resubmitReview' => (string) __('diary.lifecycle.action.resubmit_review'),
            'approveAndComplete' => (string) __('diary.lifecycle.action.approve_and_complete'),
            default => $action,
        };
    }
}
