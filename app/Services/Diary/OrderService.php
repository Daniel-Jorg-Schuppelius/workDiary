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

use App\Enums\Diary\Status;
use App\Enums\Protocol\ProtocolStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\{DiaryEntry, DiaryEntryEvent, Protocol, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService {
    public function accept(DiaryEntry $entry, User $actor): DiaryEntry {
        return $this->transition($entry, $actor, 'accept', Status::Accepted, [
            'accepted_at' => CarbonImmutable::now(),
            'accepted_by_user_id' => $actor->id,
        ]);
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
        ], payload: ['wait_seconds' => $waitSeconds]);
    }

    public function complete(DiaryEntry $entry, User $actor, string $summary): DiaryEntry {
        return $this->transition($entry, $actor, 'complete', Status::Completed, [
            'completed_at' => CarbonImmutable::now(),
            'completed_by_user_id' => $actor->id,
            'completion_summary' => $summary,
        ], $summary);
    }

    public function handover(DiaryEntry $entry, User $actor, Protocol $protocol): DiaryEntry {
        if (
            $protocol->status !== ProtocolStatus::Signed
            || $protocol->subject_type !== DiaryEntry::class
            || (int) $protocol->subject_id !== (int) $entry->id
        ) {
            throw new InvalidArgumentException((string) __('Für die Abnahme ist ein signiertes Protokoll dieses Auftrags erforderlich.'));
        }

        return $this->transition($entry, $actor, 'handover', Status::AcceptedFinal, [
            'accepted_final_at' => $protocol->signed_at ?? CarbonImmutable::now(),
            'accepted_final_by' => $actor->id,
            'protocol_id' => $protocol->id,
        ], payload: ['protocol_id' => $protocol->id]);
    }

    public function markInvoiced(DiaryEntry $entry, User $actor, string $reference): DiaryEntry {
        return $this->transition($entry, $actor, 'markInvoiced', Status::Invoiced, [
            'invoiced_at' => CarbonImmutable::now(),
            'invoice_reference' => $reference,
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
        User $actor,
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

            if (! in_array($action, $from->allowedActions(), true)) {
                throw InvalidOrderTransitionException::forAction($from, $this->actionLabel($action));
            }

            $locked->forceFill($attributes + ['status' => $target->value])->save();

            DiaryEntryEvent::query()->create([
                'diary_entry_id' => $locked->id,
                'organization_id' => $locked->organization_id,
                'event' => 'order.' . $action,
                'from_status' => $from->slug(),
                'to_status' => $target->slug(),
                'actor_user_id' => $actor->id,
                'actor_kind' => 'user',
                'note' => $note,
                'payload' => $payload ?: null,
                'occurred_at' => CarbonImmutable::now(),
            ]);

            return $locked->refresh();
        });
    }

    private function actionLabel(string $action): string {
        return match ($action) {
            'accept' => (string) __('Annehmen'),
            'start' => (string) __('Beginnen'),
            'pause' => (string) __('Pausieren'),
            'resume' => (string) __('Fortsetzen'),
            'complete' => (string) __('Abschließen'),
            'handover' => (string) __('Abnahme starten'),
            'markInvoiced' => (string) __('Als berechnet markieren'),
            'cancel' => (string) __('Stornieren'),
            default => $action,
        };
    }
}
