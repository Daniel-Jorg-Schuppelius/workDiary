<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementDigestMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope};

/** Leitungsbericht als PDF-Anhang (Feature 184); Inhalt nach den Rechten des Empfängers. */
class ManagementDigestMail extends Mailable {
    use Queueable;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $organizationName,
        public readonly string $rangeLabel,
        private readonly string $pdfBytes,
        private readonly string $filename,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(subject: __('reporting.management.digest.subject', ['organization' => $this->organizationName, 'range' => $this->rangeLabel]));
    }

    public function content(): Content {
        return new Content(markdown: 'mail.management-digest', with: ['rangeLabel' => $this->rangeLabel, 'recipientName' => $this->recipientName]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array {
        $bytes = $this->pdfBytes;

        return [Attachment::fromData(static fn (): string => $bytes, $this->filename)->withMime('application/pdf')];
    }
}
