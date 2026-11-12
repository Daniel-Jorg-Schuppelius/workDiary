<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolSignatureRequestMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Mail;

use App\Models\Protocol\{Protocol, ProtocolSignatureToken};
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Signaturlink eines Protokolls an die unterzeichnende Person (MVP-1572). Der
 * Link trägt den Klartext-Token, der sonst nur einmal auf der Seite steht.
 */
class ProtocolSignatureRequestMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public Protocol $protocol, public ProtocolSignatureToken $token, public string $signUrl) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('protocol.mail.subject', ['title' => $this->protocol->title]));
    }

    public function content(): Content {
        return new Content(markdown: 'mail.protocol-signature-request', with: [
            'protocol' => $this->protocol,
            'token' => $this->token,
            'signUrl' => $this->signUrl,
        ]);
    }
}
