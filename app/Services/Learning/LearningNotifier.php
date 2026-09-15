<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningNotifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Notification\NotificationEvent;
use App\Models\EventParticipant;
use App\Models\Learning\{LearningBooking, LearningCertificate, LearningEnrollment, LearningSubmission, LearningTimeSession};
use App\Services\Notification\NotificationDispatcher;

/**
 * Benachrichtigungen der Lernplattform (Feature 149, MVP-780): eine Stelle,
 * die je Ereignis den Payload baut — Kurstitel als Fachdatum, Texte als
 * `message_key` + Params, damit jede Empfängerin ihre Sprache bekommt.
 *
 * Empfänger, Kanäle und Eskalation entscheidet die Org-Regel
 * (`NotificationRule`), nicht dieser Dienst. Externe ohne Konto erreichen
 * die Regeln nicht — sie bekommen ihre Post über den Einstiegslink.
 */
class LearningNotifier {
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function enrolled(LearningEnrollment $enrollment): int {
        $user = $enrollment->user;
        if ($user === null) {
            return 0;
        }

        $course = $this->courseTitle($enrollment);
        $due = $enrollment->due_at;
        $key = $due !== null ? 'notification.message.learning_enrolled_with_due' : 'notification.message.learning_enrolled';
        $params = $due !== null ? ['course' => $course, 'date' => $due->toDateString()] : ['course' => $course];

        return $this->dispatcher->notify(NotificationEvent::LearningEnrolled, $enrollment, $user, [
            'title' => $course,
            'message' => (string) __($key, $due !== null ? ['course' => $course, 'date' => $due->format('d.m.Y')] : ['course' => $course]),
            'message_key' => $key,
            'message_params' => $params,
            'url' => $this->enrollmentUrl($enrollment),
            'due_at' => $enrollment->due_at,
        ]);
    }

    public function submissionReceived(LearningSubmission $submission): int {
        $enrollment = $submission->enrollment;
        if ($enrollment === null) {
            return 0;
        }

        $course = $this->courseTitle($enrollment);
        $params = [
            'name' => $enrollment->learnerName(),
            'assignment' => (string) ($submission->assignment->title ?? ''),
            'course' => $course,
        ];

        return $this->dispatcher->notify(
            NotificationEvent::LearningSubmissionReceived,
            $submission,
            $enrollment->course?->owner,
            [
                'title' => $course,
                'message' => (string) __('notification.message.learning_submission_received', $params),
                'message_key' => 'notification.message.learning_submission_received',
                'message_params' => $params,
                'url' => $this->safeRoute('learning.grading.submission', $submission),
            ],
        );
    }

    public function graded(LearningSubmission $submission, bool $returned = false): int {
        $enrollment = $submission->enrollment;
        $user = $enrollment?->user;
        if ($enrollment === null || $user === null) {
            return 0;
        }

        $course = $this->courseTitle($enrollment);
        $key = $returned ? 'notification.message.learning_returned' : 'notification.message.learning_graded';
        $params = ['assignment' => (string) ($submission->assignment->title ?? ''), 'course' => $course];

        return $this->dispatcher->notify(NotificationEvent::LearningGraded, $submission, $user, [
            'title' => $course,
            'message' => (string) __($key, $params),
            'message_key' => $key,
            'message_params' => $params,
            'url' => $this->enrollmentUrl($enrollment),
        ]);
    }

    public function certificateIssued(LearningCertificate $certificate): int {
        $enrollment = $certificate->enrollment;
        $user = $enrollment?->user;
        if ($enrollment === null || $user === null) {
            return 0;
        }

        $course = $this->courseTitle($enrollment);
        $params = ['number' => (string) $certificate->number, 'course' => $course];

        return $this->dispatcher->notify(NotificationEvent::LearningCertificateIssued, $certificate, $user, [
            'title' => $course,
            'message' => (string) __('notification.message.learning_certificate_issued', $params),
            'message_key' => 'notification.message.learning_certificate_issued',
            'message_params' => $params,
            'url' => $this->enrollmentUrl($enrollment),
        ]);
    }

    public function waitlistPromoted(EventParticipant $participant): int {
        $user = $participant->user;
        $event = $participant->event;
        if ($user === null || $event === null) {
            return 0;
        }

        $startsAt = $event->started_at;
        $params = [
            'title' => (string) $event->title,
            'date' => $startsAt->toIso8601String(),
        ];

        return $this->dispatcher->notify(NotificationEvent::LearningWaitlistPromoted, $participant, $user, [
            'title' => (string) $event->title,
            'message' => (string) __('notification.message.learning_waitlist_promoted', [
                'title' => $event->title,
                'date' => $startsAt->format('d.m.Y H:i'),
            ]),
            'message_key' => 'notification.message.learning_waitlist_promoted',
            'message_params' => $params,
            'url' => $this->safeRoute('learning.my.index'),
            'due_at' => $startsAt,
        ]);
    }

    public function bookingDecided(LearningBooking $booking, bool $confirmed): int {
        $user = $booking->user;
        if ($user === null) {
            return 0;
        }

        $course = (string) ($booking->course->title ?? '');
        $key = $confirmed ? 'notification.message.learning_booking_confirmed' : 'notification.message.learning_booking_rejected';

        return $this->dispatcher->notify(NotificationEvent::LearningBookingDecided, $booking, $user, [
            'title' => $course,
            'message' => (string) __($key, ['course' => $course]),
            'message_key' => $key,
            'message_params' => ['course' => $course],
            'url' => $this->safeRoute('learning.my.index'),
        ]);
    }

    public function timeApprovalRequested(LearningTimeSession $session): int {
        $enrollment = $session->enrollment;
        if ($enrollment === null) {
            return 0;
        }

        $course = $this->courseTitle($enrollment);
        $params = [
            'name' => $enrollment->learnerName(),
            'minutes' => (int) round(((int) $session->active_seconds) / 60),
            'course' => $course,
        ];

        return $this->dispatcher->notify(NotificationEvent::LearningTimeApprovalRequested, $session, null, [
            'title' => $course,
            'message' => (string) __('notification.message.learning_time_approval_requested', $params),
            'message_key' => 'notification.message.learning_time_approval_requested',
            'message_params' => $params,
            'url' => $this->safeRoute('learning.time-approvals.index'),
        ]);
    }

    private function courseTitle(LearningEnrollment $enrollment): string {
        return (string) ($enrollment->course->title ?? '');
    }

    private function enrollmentUrl(LearningEnrollment $enrollment): ?string {
        return $this->safeRoute('learning.my.show', $enrollment);
    }

    private function safeRoute(string $name, mixed $parameter = null): ?string {
        try {
            return $parameter === null ? route($name) : route($name, $parameter);
        } catch (\Throwable) {
            return null;
        }
    }
}
