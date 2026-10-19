<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteReplyMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Address, Content, Envelope, Headers};
use Illuminate\Queue\SerializesModels;

/**
 * Antwort auf eine eingegangene E-Mail-Notiz (Kommunikation): reiner Text mit
 * zitierter Ursprungsnachricht, eigener Message-ID (Versandverweis an der
 * ausgehenden Notiz) und In-Reply-To/References auf die Ursprungsmail, damit
 * Mail-Clients den Faden fortsetzen. Reply-To zeigt auf das Eingangspostfach,
 * damit die nächste Antwort des Empfängers wieder im Mail-Eingang landet.
 */
class CommunicationNoteReplyMail extends Mailable {
    use SerializesModels;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $messageText,
        public readonly string $messageId,
        public readonly ?string $inReplyTo,
        public readonly ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(
            subject: $this->mailSubject,
            replyTo: $this->replyToAddress !== null ? [new Address($this->replyToAddress)] : [],
        );
    }

    public function content(): Content {
        return new Content(text: 'mail.document-text', with: ['text' => $this->messageText]);
    }

    public function headers(): Headers {
        $parent = $this->inReplyTo !== null ? '<' . trim($this->inReplyTo, "<> \t") . '>' : null;

        return new Headers(
            messageId: $this->messageId,
            references: $parent !== null ? [$parent] : [],
            text: $parent !== null ? ['In-Reply-To' => $parent] : [],
        );
    }
}
