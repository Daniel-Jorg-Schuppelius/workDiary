<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAccessLinkMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Communication\ExternalParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Einstiegslink für Lernende ohne Benutzerkonto (Feature 149, MVP-778). Der
 * Link trägt den Klartext-Token, der sonst nirgends existiert — die Mail ist
 * der einzige Zustellweg; Betreff und Log bleiben ohne Token.
 */
class LearningAccessLinkMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ExternalParticipant $participant,
        public string $courseTitle,
        public string $accessUrl,
        public Carbon $expiresAt,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('learning.mail.access_subject', ['course' => $this->courseTitle]));
    }

    public function content(): Content {
        return new Content(markdown: 'mail.learning-access-link', with: [
            'participant' => $this->participant,
            'courseTitle' => $this->courseTitle,
            'accessUrl' => $this->accessUrl,
            'expiresAt' => $this->expiresAt,
        ]);
    }
}
