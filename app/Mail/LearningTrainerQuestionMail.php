<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTrainerQuestionMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Address, Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Frage einer lernenden Person an die verantwortliche Person eines Kurses
 * (Feature 149, MVP-789) — der Weg ohne Helpdesk-Modul. Antwort-Adresse ist
 * die lernende Person, damit ein „Antworten" direkt bei ihr landet.
 */
class LearningTrainerQuestionMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $learner,
        public string $courseTitle,
        public string $question,
    ) {}

    public function envelope(): Envelope {
        $replyTo = $this->learner->email !== ''
            ? [new Address($this->learner->email, $this->learner->name)]
            : [];

        return new Envelope(
            subject: (string) __('learning.mail.question_subject', ['course' => $this->courseTitle]),
            replyTo: $replyTo,
        );
    }

    public function content(): Content {
        return new Content(view: 'mail.learning-trainer-question', with: [
            'learner' => $this->learner,
            'courseTitle' => $this->courseTitle,
            'question' => $this->question,
        ]);
    }
}
