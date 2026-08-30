<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningBookingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\{LearningAccessKind, LearningBookingStatus, LearningCourseStatus, LearningEnrollmentSource};
use App\Models\{Customer, ExternalParticipant, User};
use App\Models\Learning\{LearningBooking, LearningCourse};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kursbuchung und Verkauf (Feature 149, MVP-744) — einzige Schreibstelle.
 *
 * Die Regeln:
 *  1. **Zweiphasig:** eine Buchung ist zunächst eine Anfrage. Der Zugang
 *     entsteht erst mit der Zusage — nie durch die Anfrage selbst.
 *  2. **Preis wird bei der Zusage eingefroren.** Eine spätere Änderung am
 *     Artikel verteuert eine erteilte Zusage nicht.
 *  3. **Keine automatische Rechnung.** Die Rechnungshoheit kann extern
 *     liegen; die Buchung markiert sich als abrechenbar, fakturiert wird
 *     in einem eigenen Schritt.
 *  4. Gebucht wird nur, was **freigegeben und buchbar** ist.
 */
class LearningBookingService {
    public function __construct(
        private readonly LearningEnrollmentService $enrollments,
    ) {}

    /**
     * Buchungsanfrage stellen.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function request(
        LearningCourse $course,
        User|ExternalParticipant|null $booker,
        ?Customer $customer = null,
        array $attributes = [],
        ?Carbon $now = null,
    ): LearningBooking {
        $now ??= Carbon::now();
        $this->guardBookable($course);

        if ($booker === null && $customer === null) {
            throw ValidationException::withMessages([
                'booking' => (string) __('learning.errors.booking_without_subject'),
            ]);
        }

        $isUser = $booker instanceof User;
        // IDs vor der Abfrage auflösen: in einer Closure ist der Typ des
        // Subjekts nicht mehr eingeengt.
        $userId = $isUser ? $booker->id : null;
        $externalId = $booker instanceof ExternalParticipant ? $booker->id : null;
        $customerId = $customer?->id;

        // Eine offene Anfrage derselben Person bleibt eine Anfrage — doppelte
        // Klicks erzeugen keine zweite Buchung.
        $existing = LearningBooking::query()
            ->where('learning_course_id', $course->id)
            ->where('status', LearningBookingStatus::Requested->value)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->when($externalId !== null, fn ($q) => $q->where('external_participant_id', $externalId))
            ->when($booker === null && $customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return LearningBooking::query()->create([
            'organization_id' => $course->organization_id,
            'learning_course_id' => $course->id,
            'user_id' => $userId,
            'external_participant_id' => $externalId,
            'customer_id' => $customerId,
            'status' => LearningBookingStatus::Requested->value,
            'seats' => max(1, (int) ($attributes['seats'] ?? 1)),
            'requested_at' => $now,
        ]);
    }

    /**
     * Zusage: friert den Preis ein und schafft den Zugang. Ohne Preis am
     * Kurs bleibt die Buchung kostenfrei — das ist der Regelfall bei
     * internen Kursen.
     */
    public function confirm(LearningBooking $booking, ?User $actor = null, ?string $note = null, ?Carbon $now = null): LearningBooking {
        $now ??= Carbon::now();

        if (! $booking->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.booking_not_open'),
            ]);
        }

        $course = $booking->course;

        if ($course === null) {
            throw ValidationException::withMessages([
                'course' => (string) __('learning.errors.booking_without_course'),
            ]);
        }

        return DB::transaction(function () use ($booking, $course, $actor, $note, $now): LearningBooking {
            $article = $course->article;
            // `default_sale_price` ist ein Money-ValueObject — Betrag und
            // Währung kommen daraus, nicht aus getrennten Spalten.
            $price = $article?->default_sale_price;

            $enrollment = null;
            $subject = $booking->user ?? $booking->externalParticipant;

            // Ohne Person (reine Kundenbuchung über mehrere Plätze) entsteht
            // noch keine Einschreibung — die Teilnehmenden werden später
            // benannt.
            if ($subject !== null) {
                $enrollment = $this->enrollments->enroll($course, $subject, [
                    'source' => LearningEnrollmentSource::Booking->value,
                    'assigned_by_user_id' => $actor?->id,
                ]);
            }

            $booking->update([
                'status' => LearningBookingStatus::Confirmed->value,
                'article_id' => $article?->id,
                'unit_price' => $price?->getAmount(),
                'currency' => $price?->getCurrency()?->value,
                'decided_at' => $now,
                'decided_by_user_id' => $actor?->id,
                'decision_note' => $note,
                'learning_enrollment_id' => $enrollment?->id,
                'is_billable' => $price !== null,
            ]);

            return $booking->refresh();
        });
    }

    public function reject(LearningBooking $booking, string $reason, ?User $actor = null, ?Carbon $now = null): LearningBooking {
        if (! $booking->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.booking_not_open'),
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => (string) __('learning.errors.booking_reject_reason'),
            ]);
        }

        $booking->update([
            'status' => LearningBookingStatus::Rejected->value,
            'decided_at' => $now ?? Carbon::now(),
            'decided_by_user_id' => $actor?->id,
            'decision_note' => $reason,
        ]);

        return $booking->refresh();
    }

    /**
     * Storno. Eine bereits abgerechnete Buchung wird nicht storniert — das
     * wäre eine stille Gutschrift; dafür ist die Faktura zuständig.
     */
    public function cancel(LearningBooking $booking, ?User $actor = null, ?Carbon $now = null): LearningBooking {
        if ($booking->billed_at !== null) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.booking_already_billed'),
            ]);
        }

        $booking->update([
            'status' => LearningBookingStatus::Cancelled->value,
            'decided_at' => $now ?? Carbon::now(),
            'decided_by_user_id' => $actor?->id,
            'is_billable' => false,
        ]);

        return $booking->refresh();
    }

    /** Als fakturiert markieren — der Beleg selbst entsteht in der Faktura. */
    public function markBilled(LearningBooking $booking, ?Carbon $now = null): LearningBooking {
        if (! $booking->isOpenForBilling()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.booking_not_billable'),
            ]);
        }

        $booking->update(['billed_at' => $now ?? Carbon::now()]);

        return $booking->refresh();
    }

    private function guardBookable(LearningCourse $course): void {
        if ($course->status !== LearningCourseStatus::Released) {
            throw ValidationException::withMessages([
                'course' => (string) __('learning.errors.booking_requires_release'),
            ]);
        }

        if ($course->access_kind !== LearningAccessKind::Bookable) {
            throw ValidationException::withMessages([
                'course' => (string) __('learning.errors.course_not_bookable'),
            ]);
        }
    }
}
