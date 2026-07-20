<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Communication;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType, CommunicationVisibility, ParticipantParty};
use App\Models\{CommunicationNote, User};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service für Kommunikationsnotizen (MVP-012).
 *
 * Erzwingt die Geschäftsregeln aus ../WorkDiary-Architecture/kommunikationsnotizen.md §10
 * (Internal-Kaskade, Vertraulichkeit, Fristen) und schreibt die fachlichen
 * Audit-Events aus §8 über den Auditable-Mechanismus — nie roh in audit_logs.
 */
class CommunicationNoteService {
    /**
     * Legt eine neue Kommunikationsnotiz inkl. Beteiligten an.
     *
     * @param  Model  $notable  DiaryEntry, Customer oder Project
     * @param  array<string, mixed>  $attributes
     */
    public function create(Model $notable, User $creator, array $attributes): CommunicationNote {
        $type = $this->parseType((string) ($attributes['type'] ?? ''));
        $direction = $this->parseDirection((string) ($attributes['direction'] ?? ''));
        $visibility = CommunicationVisibility::tryFrom((string) ($attributes['visibility'] ?? CommunicationVisibility::Internal->value))
            ?? CommunicationVisibility::Internal;
        $confidential = (bool) ($attributes['confidential'] ?? false);
        $occurredAt = Carbon::parse((string) ($attributes['occurred_at'] ?? 'now'));
        $dueAt = filled($attributes['next_action_due_at'] ?? null)
            ? Carbon::parse((string) $attributes['next_action_due_at'])
            : null;

        $this->assertConsistency($type, $direction, $visibility, $confidential, $occurredAt, $dueAt);

        $note = DB::transaction(function () use ($notable, $creator, $attributes, $type, $direction, $visibility, $confidential, $occurredAt, $dueAt): CommunicationNote {
            $note = CommunicationNote::query()->create([
                'organization_id' => $notable->getAttribute('organization_id') ?: $creator->organization_id,
                'notable_type' => $notable::class,
                'notable_id' => $notable->getKey(),
                'type' => $type->value,
                'direction' => $direction->value,
                'occurred_at' => $occurredAt,
                'subject' => $attributes['subject'],
                'body' => $attributes['body'],
                'result' => $attributes['result'] ?? null,
                'next_action' => $attributes['next_action'] ?? null,
                'next_action_due_at' => $dueAt,
                'next_action_user_id' => filled($attributes['next_action_user_id'] ?? null) ? (int) $attributes['next_action_user_id'] : null,
                'visibility' => $visibility->value,
                'confidential' => $confidential,
                'created_by_user_id' => $creator->id,
            ]);

            $this->syncParticipants($note, $attributes['participants'] ?? []);

            if ($confidential) {
                $note->audit('communication.confidential.set', ['actor_user_id' => $creator->id]);
            }

            return $note->fresh(['participants']) ?? $note;
        });

        // Telemetry-Light (Feature 036): aggregierter Org-Tageszähler, fire-and-forget.
        app(\App\Services\Metrics\OperationsMetricsService::class)->increment('communications.created', (int) $note->organization_id);

        return $note;
    }

    /**
     * Aktualisiert eine Notiz (Felder + Beteiligte). `updated`-Diff kommt
     * automatisch über den Auditable-Trait.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(CommunicationNote $note, User $actor, array $attributes): CommunicationNote {
        $type = array_key_exists('type', $attributes) ? $this->parseType((string) $attributes['type']) : $note->type;
        $direction = array_key_exists('direction', $attributes) ? $this->parseDirection((string) $attributes['direction']) : $note->direction;
        $visibility = array_key_exists('visibility', $attributes)
            ? (CommunicationVisibility::tryFrom((string) $attributes['visibility']) ?? $note->visibility)
            : $note->visibility;
        $confidential = array_key_exists('confidential', $attributes) ? (bool) $attributes['confidential'] : $note->confidential;
        $occurredAt = array_key_exists('occurred_at', $attributes) ? Carbon::parse((string) $attributes['occurred_at']) : $note->occurred_at;
        $dueAt = array_key_exists('next_action_due_at', $attributes)
            ? (filled($attributes['next_action_due_at']) ? Carbon::parse((string) $attributes['next_action_due_at']) : null)
            : $note->next_action_due_at;

        $this->assertConsistency($type, $direction, $visibility, $confidential, $occurredAt, $dueAt);

        return DB::transaction(function () use ($note, $actor, $attributes, $type, $direction, $visibility, $confidential, $occurredAt, $dueAt): CommunicationNote {
            unset($actor);

            $note->update([
                'type' => $type->value,
                'direction' => $direction->value,
                'visibility' => $visibility->value,
                'confidential' => $confidential,
                'occurred_at' => $occurredAt,
                'subject' => $attributes['subject'] ?? $note->subject,
                'body' => $attributes['body'] ?? $note->body,
                'result' => array_key_exists('result', $attributes) ? $attributes['result'] : $note->result,
                'next_action' => array_key_exists('next_action', $attributes) ? $attributes['next_action'] : $note->next_action,
                'next_action_due_at' => $dueAt,
                'next_action_user_id' => array_key_exists('next_action_user_id', $attributes)
                    ? (filled($attributes['next_action_user_id']) ? (int) $attributes['next_action_user_id'] : null)
                    : $note->next_action_user_id,
            ]);

            if (array_key_exists('participants', $attributes)) {
                $note->participants()->delete();
                $this->syncParticipants($note, $attributes['participants']);
            }

            return $note->fresh(['participants']) ?? $note;
        });
    }

    /** Gibt eine interne Notiz für das Kundenportal frei (§6). */
    public function publishToCustomer(CommunicationNote $note, User $actor): CommunicationNote {
        if ($note->confidential) {
            throw ValidationException::withMessages([
                'visibility' => (string) __('communication.error.confidential_not_publishable'),
            ]);
        }
        if ($note->visibility === CommunicationVisibility::Customer) {
            return $note;
        }
        if ($note->direction === CommunicationDirection::Internal) {
            throw ValidationException::withMessages([
                'visibility' => (string) __('communication.error.internal_not_publishable'),
            ]);
        }

        return DB::transaction(function () use ($note, $actor): CommunicationNote {
            $note->update(['visibility' => CommunicationVisibility::Customer->value]);
            $note->audit('communication.published', [
                'actor_user_id' => $actor->id,
                'from' => CommunicationVisibility::Internal->value,
                'to' => CommunicationVisibility::Customer->value,
            ]);

            return $note;
        });
    }

    /** Markiert eine Notiz als vertraulich; erzwingt visibility=internal (§4). */
    public function markConfidential(CommunicationNote $note, User $actor): CommunicationNote {
        if ($note->confidential) {
            return $note;
        }

        return DB::transaction(function () use ($note, $actor): CommunicationNote {
            $note->update([
                'confidential' => true,
                'visibility' => CommunicationVisibility::Internal->value,
            ]);
            $note->audit('communication.confidential.set', ['actor_user_id' => $actor->id]);

            return $note;
        });
    }

    public function unmarkConfidential(CommunicationNote $note, User $actor): CommunicationNote {
        if (! $note->confidential) {
            return $note;
        }

        return DB::transaction(function () use ($note, $actor): CommunicationNote {
            $note->update(['confidential' => false]);
            $note->audit('communication.confidential.unset', ['actor_user_id' => $actor->id]);

            return $note;
        });
    }

    /** Schließt die Folgeaktion ab (§6 communication.completeFollowup). */
    public function completeFollowup(CommunicationNote $note, User $actor): CommunicationNote {
        if ($note->next_action === null) {
            throw ValidationException::withMessages([
                'next_action' => (string) __('communication.error.no_followup'),
            ]);
        }
        if ($note->next_action_completed_at !== null) {
            return $note;
        }

        return DB::transaction(function () use ($note, $actor): CommunicationNote {
            $note->update([
                'next_action_completed_at' => Carbon::now(),
                'next_action_completed_by_user_id' => $actor->id,
            ]);
            $note->audit('communication.followup.completed', [
                'actor_user_id' => $actor->id,
                'next_action' => $note->next_action,
            ]);

            return $note;
        });
    }

    /** Soft-Delete mit Begründung (§6/§8). */
    public function delete(CommunicationNote $note, User $actor, ?string $reason = null): void {
        DB::transaction(function () use ($note, $actor, $reason): void {
            // Fachliches Event VOR dem Delete, damit das Auditable-`deleted`
            // und die Begründung gemeinsam in der Hash-Kette landen.
            $note->audit('communication.deleted', [
                'actor_user_id' => $actor->id,
                'reason' => $reason,
            ]);
            $note->delete();
        });
    }

    /**
     * Audit-Event `communication.confidential.viewed`, wenn eine vertrauliche
     * Notiz von jemand anderem als dem Erfasser geöffnet wird (§4/§8).
     */
    public function recordConfidentialView(CommunicationNote $note, User $viewer): void {
        if (! $note->confidential || (int) $note->created_by_user_id === (int) $viewer->id) {
            return;
        }

        $note->audit('communication.confidential.viewed', ['viewer_user_id' => $viewer->id]);
    }

    /**
     * Panel-Lesen vertraulicher fremder Notizen auditieren (Vollaudit
     * 2026-07, N11) — dedupliziert auf 1× je Note+Viewer+Tag.
     *
     * @param  \Illuminate\Support\Collection<int, CommunicationNote>  $notes
     */
    public function recordConfidentialViews(\Illuminate\Support\Collection $notes, ?User $viewer): void {
        if (! $viewer instanceof User) {
            return;
        }

        foreach ($notes as $note) {
            if (! $note->confidential || (int) $note->created_by_user_id === (int) $viewer->id) {
                continue;
            }

            $already = \App\Models\AuditLog::query()
                ->where('event', 'communication.confidential.viewed')
                ->where('auditable_type', CommunicationNote::class)
                ->where('auditable_id', $note->id)
                ->where('changes->viewer_user_id', $viewer->id)
                ->whereDate('created_at', now()->toDateString())
                ->exists();
            if (! $already) {
                $this->recordConfidentialView($note, $viewer);
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $participants
     */
    private function syncParticipants(CommunicationNote $note, array $participants): void {
        $seen = [];
        foreach ($participants as $participant) {
            if (! is_array($participant)) {
                continue;
            }
            $name = trim((string) ($participant['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $party = ParticipantParty::tryFrom((string) ($participant['party'] ?? '')) ?? ParticipantParty::Customer;
            $userId = filled($participant['user_id'] ?? null) ? (int) $participant['user_id'] : null;

            // Doppelte Zeilen (gleicher Name + Partei + User) leise überspringen,
            // statt am Unique-Index zu scheitern.
            $key = $name . '|' . $party->value . '|' . ($userId ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $note->participants()->create([
                'user_id' => $userId,
                'customer_contact_id' => filled($participant['customer_contact_id'] ?? null) ? (int) $participant['customer_contact_id'] : null,
                'name' => $name,
                'role' => filled($participant['role'] ?? null) ? trim((string) $participant['role']) : null,
                'party' => $party->value,
            ]);
        }
    }

    /**
     * Geschäftsregeln aus §10: Internal-Kaskade, Vertraulichkeit, Fristen.
     */
    private function assertConsistency(
        CommunicationNoteType $type,
        CommunicationDirection $direction,
        CommunicationVisibility $visibility,
        bool $confidential,
        CarbonInterface $occurredAt,
        ?CarbonInterface $nextActionDueAt,
    ): void {
        if ($type === CommunicationNoteType::Internal && $direction !== CommunicationDirection::Internal) {
            throw ValidationException::withMessages([
                'direction' => (string) __('communication.error.internal_type_requires_internal_direction'),
            ]);
        }

        if ($direction === CommunicationDirection::Internal && $visibility !== CommunicationVisibility::Internal) {
            throw ValidationException::withMessages([
                'visibility' => (string) __('communication.error.internal_direction_requires_internal_visibility'),
            ]);
        }

        if ($confidential && $visibility !== CommunicationVisibility::Internal) {
            throw ValidationException::withMessages([
                'visibility' => (string) __('communication.error.confidential_requires_internal_visibility'),
            ]);
        }

        if ($occurredAt->gt(Carbon::now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'occurred_at' => (string) __('communication.error.occurred_at_in_future'),
            ]);
        }

        if ($nextActionDueAt !== null && ! $nextActionDueAt->gt($occurredAt)) {
            throw ValidationException::withMessages([
                'next_action_due_at' => (string) __('communication.error.due_before_occurrence'),
            ]);
        }
    }

    private function parseType(string $value): CommunicationNoteType {
        $type = CommunicationNoteType::tryFrom($value);
        if (! $type instanceof CommunicationNoteType) {
            throw ValidationException::withMessages([
                'type' => (string) __('communication.error.unknown_type'),
            ]);
        }

        return $type;
    }

    private function parseDirection(string $value): CommunicationDirection {
        $direction = CommunicationDirection::tryFrom($value);
        if (! $direction instanceof CommunicationDirection) {
            throw ValidationException::withMessages([
                'direction' => (string) __('communication.error.unknown_direction'),
            ]);
        }

        return $direction;
    }
}
