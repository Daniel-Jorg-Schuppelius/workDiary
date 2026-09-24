<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Notification\NotificationEvent;
use App\Enums\ServiceTicket\{ServiceTicketKind, ServiceTicketSource};
use App\Mail\LearningTrainerQuestionMail;
use App\Models\Learning\LearningEnrollment;
use App\Models\Platform\User;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Notification\NotificationDispatcher;
use App\Services\ServiceTicket\ServiceTicketService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Frage an den Trainer (Feature 149, MVP-789). Mit Helpdesk-Modul wird ein
 * Ticket der Art „Frage" mit Bezug zur Einschreibung angelegt — die Antwort
 * läuft dann über das Ticket. Ohne Helpdesk geht die Frage als Mail an die
 * verantwortliche Person (Antwort per Mail an die lernende Person). In
 * beiden Fällen erhalten Verantwortliche und Trainer eine Benachrichtigung.
 */
class LearningQuestionService {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @return array{ticket: ServiceTicket|null, recipients: int}
     */
    public function ask(LearningEnrollment $enrollment, User $learner, string $question): array {
        $question = trim($question);
        if (mb_strlen($question) < 5) {
            throw ValidationException::withMessages([
                'question' => (string) __('learning.errors.question_too_short'),
            ]);
        }

        $course = $enrollment->course;
        $organization = $enrollment->organization;
        $recipients = $this->recipients($enrollment);
        if ($course === null || $organization === null || $recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'question' => (string) __('learning.errors.no_trainer'),
            ]);
        }

        $ticket = null;
        if ($this->features->isEnabled('module.helpdesk')) {
            $ticket = app(ServiceTicketService::class)->create($organization, $learner, [
                'kind' => ServiceTicketKind::Question->value,
                'title' => (string) __('learning.ask.ticket_title', ['course' => $course->title, 'name' => $learner->name]),
                'description' => $question,
                'source' => ServiceTicketSource::Manual->value,
                'source_reference' => 'learning:' . $enrollment->sqid,
            ]);
            // Zuständig ist die verantwortliche Person des Kurses, sonst der
            // erste Trainer — so landet die Frage nicht in einer leeren Queue.
            $ticket->forceFill(['assigned_to_user_id' => $recipients->first()->id])->save();
        } else {
            $owner = $course->owner;
            if ($owner !== null && $owner->email !== '') {
                Mail::to($owner->email)->queue(new LearningTrainerQuestionMail($learner, $course->title, $question));
            }
        }

        $params = ['name' => $learner->name, 'course' => (string) $course->title, 'question' => Str::limit($question, 200)];
        $url = $ticket !== null ? route('service-tickets.show', $ticket) : route('learning.courses.enrollments.index', $course);
        $count = 0;
        foreach ($recipients as $recipient) {
            $count += $this->dispatcher->notify(NotificationEvent::LearningQuestionAsked, $enrollment, $recipient, [
                'title' => (string) $course->title,
                'message' => (string) __('notification.message.learning_question_asked', $params),
                'message_key' => 'notification.message.learning_question_asked',
                'message_params' => $params,
                'url' => $url,
            ]);
        }

        return ['ticket' => $ticket, 'recipients' => $count];
    }

    /**
     * Verantwortliche Person zuerst, dann die Trainer — ohne Dubletten.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function recipients(LearningEnrollment $enrollment): \Illuminate\Support\Collection {
        $course = $enrollment->course;
        if ($course === null) {
            return collect();
        }

        return collect([$course->owner])
            ->merge($course->trainers()->get())
            ->filter()
            ->unique('id')
            ->values();
    }
}
