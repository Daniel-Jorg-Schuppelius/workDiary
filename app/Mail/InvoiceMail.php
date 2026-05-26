<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Versendet eine Rechnung (oder Gutschrift) als E-Mail mit PDF-Anhang.
 *
 * - Subject + HTML- und Text-Body werden vom Controller bereits aus dem
 *   {@see \App\Models\InvoiceMailTemplate} gerendert übergeben (XSS-sicher).
 * - Empfänger (To/CC/BCC) werden im Controller via ->to()/->cc()/->bcc()
 *   gesetzt, NICHT in der Envelope hardcoded — so bleibt die Mailable
 *   Multi-Empfänger-fähig.
 * - PDF wird in attachments() on-the-fly gerendert (kein großer Anhang in
 *   der Queue-Payload).
 */
class InvoiceMail extends Mailable implements ShouldQueue {
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $renderedSubject,
        public string $renderedHtml,
        public string $renderedText,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(subject: $this->renderedSubject);
    }

    public function content(): Content {
        return new Content(
            view: 'mail.invoice',
            text: 'mail.invoice-text',
            with: [
                'invoice' => $this->invoice,
                'html' => $this->renderedHtml,
                'text' => $this->renderedText,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array {
        $this->invoice->loadMissing(['items', 'customer', 'project', 'parent']);

        $bytes = Pdf::loadView('invoices.pdf', ['invoice' => $this->invoice])
            ->setPaper('a4')
            ->output();

        $prefix = $this->invoice->isCreditNote() ? 'gutschrift' : 'rechnung';
        $filename = sprintf('%s-%s.pdf', $prefix, $this->invoice->number);

        return [
            Attachment::fromData(static fn(): string => $bytes, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
