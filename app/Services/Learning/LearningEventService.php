<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningEventService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Event\{ParticipantRole, ParticipantStatus};
use App\Models\{Event, EventParticipant, User};
use App\Models\Learning\{LearningEnrollment, LearningUnit};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Präsenztermine im Kurs (Feature 149, MVP-741) — einzige Schreibstelle
 * für Anmeldung, Absage, Warteliste und Check-in.
 *
 * Der Termin selbst bleibt ein `Event` (Feature 028); hier entsteht kein
 * zweiter Terminkalender und keine zweite Teilnehmerliste.
 *
 * Die Regeln:
 *  1. **Kapazität zählt:** ist der Termin voll, landet die Anmeldung auf
 *     der Warteliste statt abgelehnt zu werden.
 *  2. **Ein frei werdender Platz rückt automatisch nach** — sonst bleibt
 *     ein Platz leer, während jemand wartet.
 *  3. **Anwesenheit ist die Wahrheit**, nicht die Anmeldung: erst
 *     `attended` schließt die Lerneinheit ab.
 *  4. Fristen werden gegen den Terminbeginn geprüft, nicht gegen ein
 *     Kursdatum.
 */
class LearningEventService {
    /** Check-in öffnet eine halbe Stunde vor Beginn … */
    public const CHECKIN_BEFORE_MINUTES = 30;

    /** … und schließt zwei Stunden nach Ende. */
    public const CHECKIN_AFTER_MINUTES = 120;

    public function __construct(
        private readonly LearningEnrollmentService $enrollments,
    ) {}

    /** Anmelden — oder auf die Warteliste, wenn der Termin voll ist. */
    public function register(LearningEnrollment $enrollment, LearningUnit $unit, ?Carbon $now = null): EventParticipant {
        $now ??= Carbon::now();
        $event = $this->eventOf($unit);
        $user = $enrollment->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'user' => (string) __('learning.errors.event_needs_user'),
            ]);
        }

        $this->guardRegistrationOpen($unit, $event, $now);

        return DB::transaction(function () use ($event, $user, $enrollment): EventParticipant {
            $existing = $this->participantFor($event, $user);

            if ($existing !== null && $existing->status !== ParticipantStatus::Declined) {
                return $existing;
            }

            $status = $this->hasFreeSeat($event)
                ? ParticipantStatus::Accepted
                : ParticipantStatus::Waitlisted;

            if ($existing !== null) {
                $existing->update(['status' => $status->value]);

                return $existing->refresh();
            }

            return EventParticipant::query()->create([
                'organization_id' => $enrollment->organization_id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'role' => ParticipantRole::Attendee->value,
                'status' => $status->value,
            ]);
        });
    }

    /** Absagen — und den nächsten Platz auf der Warteliste nachrücken lassen. */
    public function cancel(LearningEnrollment $enrollment, LearningUnit $unit, ?Carbon $now = null): void {
        $now ??= Carbon::now();
        $event = $this->eventOf($unit);
        $user = $enrollment->user;

        if ($user === null) {
            return;
        }

        $lead = $unit->cancellation_lead_hours;
        if ($lead !== null && $event->started_at->copy()->subHours($lead)->lessThan($now)) {
            throw ValidationException::withMessages([
                'event' => (string) __('learning.errors.cancellation_deadline_passed'),
            ]);
        }

        DB::transaction(function () use ($event, $user): void {
            $participant = $this->participantFor($event, $user);

            if ($participant === null) {
                return;
            }

            $participant->update(['status' => ParticipantStatus::Declined->value]);
            $this->promoteFromWaitlist($event);
        });
    }

    /**
     * Check-in: die Anwesenheit wird bestätigt und schließt die
     * Lerneinheit ab. Erst hier — eine Anmeldung ist kein Nachweis.
     */
    public function checkIn(LearningEnrollment $enrollment, LearningUnit $unit, ?User $actor = null): EventParticipant {
        $event = $this->eventOf($unit);
        $user = $enrollment->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'user' => (string) __('learning.errors.event_needs_user'),
            ]);
        }

        $participant = $this->participantFor($event, $user);

        if ($participant === null || $participant->status === ParticipantStatus::Waitlisted) {
            throw ValidationException::withMessages([
                'event' => (string) __('learning.errors.not_registered'),
            ]);
        }

        // Selbst-Check-in nur im Zeitfenster; die Kursleitung darf auch
        // nachtragen (sie hat die Liste gesehen, der QR-Code niemanden).
        if ($actor !== null && $actor->id === $user->id && ! $this->isCheckInOpen($event)) {
            throw ValidationException::withMessages([
                'event' => (string) __('learning.errors.checkin_closed'),
            ]);
        }

        return DB::transaction(function () use ($participant, $enrollment, $unit): EventParticipant {
            $participant->update(['status' => ParticipantStatus::Attended->value]);

            $this->enrollments->completeUnit($enrollment, $unit);

            return $participant->refresh();
        });
    }

    /**
     * Darf zu diesem Zeitpunkt eingecheckt werden?
     *
     * Ohne Zeitfenster wäre der QR-Code ein Dauerticket: wer ihn abfotografiert,
     * bestätigte seine Anwesenheit auch noch eine Woche später vom Sofa aus.
     * Deshalb ab :before vor Beginn bis :after nach Ende.
     */
    /** @return array{0: Carbon, 1: Carbon} */
    public function checkInWindow(Event $event): array {
        $start = $event->started_at->copy();
        $end = $event->ended_at->copy();

        return [
            $start->copy()->subMinutes(self::CHECKIN_BEFORE_MINUTES),
            $end->copy()->addMinutes(self::CHECKIN_AFTER_MINUTES),
        ];
    }

    public function isCheckInOpen(Event $event, ?Carbon $now = null): bool {
        [$from, $until] = $this->checkInWindow($event);
        $now ??= Carbon::now();

        return $now->betweenIncluded($from, $until);
    }

    /** Rückt die erste wartende Person nach, wenn ein Platz frei ist. */
    public function promoteFromWaitlist(Event $event): ?EventParticipant {
        if (! $this->hasFreeSeat($event)) {
            return null;
        }

        $next = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', ParticipantStatus::Waitlisted->value)
            ->orderBy('id')
            ->first();

        if ($next === null) {
            return null;
        }

        $next->update(['status' => ParticipantStatus::Accepted->value]);

        return $next->refresh();
    }

    /** Freie Plätze: ohne Obergrenze ist immer Platz. */
    public function hasFreeSeat(Event $event): bool {
        if ($event->max_participants === null) {
            return true;
        }

        $taken = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                ParticipantStatus::Accepted->value,
                ParticipantStatus::Attended->value,
            ])
            ->count();

        return $taken < $event->max_participants;
    }

    private function guardRegistrationOpen(LearningUnit $unit, Event $event, Carbon $now): void {
        if ($event->started_at->lessThan($now)) {
            throw ValidationException::withMessages([
                'event' => (string) __('learning.errors.event_already_started'),
            ]);
        }

        $lead = $unit->registration_lead_hours;
        if ($lead !== null && $event->started_at->copy()->subHours($lead)->lessThan($now)) {
            throw ValidationException::withMessages([
                'event' => (string) __('learning.errors.registration_deadline_passed'),
            ]);
        }
    }

    private function participantFor(Event $event, User $user): ?EventParticipant {
        return EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function eventOf(LearningUnit $unit): Event {
        $event = $unit->event;

        if ($event === null) {
            throw ValidationException::withMessages([
                'event' => (string) __('learning.errors.unit_without_event'),
            ]);
        }

        return $event;
    }
}
