<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyInvitationMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Survey\Survey;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Einladung zu einer Umfrage (Feature 090): trägt den Einmal-Link; der
 * Klartext-Token existiert nur in dieser Mail.
 */
class SurveyInvitationMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Survey $survey,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('Ihre Meinung zu :title', ['title' => $this->survey->title]));
    }

    public function content(): Content {
        return new Content(markdown: 'emails.survey-invitation', with: [
            'url' => route('surveys.public-show', ['token' => $this->token]),
        ]);
    }
}
