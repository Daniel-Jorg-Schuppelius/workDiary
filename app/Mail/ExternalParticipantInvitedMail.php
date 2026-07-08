<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalParticipantInvitedMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Mail;

use App\Models\ExternalParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Versendet den login-freien Einmal-Zugangslink an einen externen Beteiligten
 * (Feature 033, Rang 29). Der Link (`route('external.show', token)`) trägt den
 * Klartext-Token, der nur einmal existiert; die Mail ist der einzige dauerhafte
 * Zustellweg. Kein Passwort/keine Zugangsdaten im Klartext.
 */
class ExternalParticipantInvitedMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public ExternalParticipant $participant, public string $accessUrl) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('external.mail.subject'));
    }

    public function content(): Content {
        return new Content(view: 'mail.external-participant-invited', with: [
            'participant' => $this->participant,
            'accessUrl' => $this->accessUrl,
        ]);
    }
}
