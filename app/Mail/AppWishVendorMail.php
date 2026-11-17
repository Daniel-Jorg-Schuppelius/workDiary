<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishVendorMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Address, Content, Envelope};
use Illuminate\Support\HtmlString;

/**
 * Herstellerkanal (Feature 206, MVP-1677): ein Wunsch einer selbst
 * betriebenen Installation als strukturierte Textmail an die
 * Herstelleradresse. Inhalt und Antwortadresse baut
 * {@see \App\Services\Wishes\VendorChannel}; die Vorschau rendert genau
 * diese Mail. Antworten gehen an den Plattform-Admin, der sie sendet.
 */
class AppWishVendorMail extends Mailable {
    public function __construct(
        public readonly string $mailSubject,
        public readonly string $messageText,
        public readonly string $replyToAddress,
        public readonly string $replyToName,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(
            subject: $this->mailSubject,
            replyTo: [new Address($this->replyToAddress, $this->replyToName)],
        );
    }

    public function content(): Content {
        // Reine Textmail: als Htmlable übergeben, damit Blade weder `&` noch Anführungszeichen
        // in Entitäten verwandelt — hinaus geht genau der Text der Vorschau (es gibt keinen HTML-Teil).
        return new Content(text: 'mail.document-text', with: ['text' => new HtmlString($this->messageText)]);
    }
}
