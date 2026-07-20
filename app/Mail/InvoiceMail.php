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
use App\Services\Invoicing\InvoicePdfRenderer;
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
        public ?int $dispatchId = null,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(subject: $this->renderedSubject);
    }

    public function headers(): \Illuminate\Mail\Mailables\Headers {
        // Vollaudit 2026-07 (M26): Dispatch-Referenz für den Zustellnachweis
        // ({@see \App\Listeners\RecordInvoiceMailDelivery}).
        return new \Illuminate\Mail\Mailables\Headers(text: array_filter([
            \App\Listeners\RecordInvoiceMailDelivery::HEADER => $this->dispatchId !== null ? (string) $this->dispatchId : null,
        ]));
    }

    /** Queue-Fehlschlag → Zustellnachweis auf failed (Vollaudit 2026-07, M26). */
    public function failed(\Throwable $exception): void {
        if ($this->dispatchId === null) {
            return;
        }
        $dispatch = \App\Models\InvoiceDispatch::query()->withoutGlobalScopes()->find($this->dispatchId);
        $dispatch?->forceFill([
            'status' => 'failed',
            'meta' => [...(array) $dispatch->meta, 'error' => mb_substr($exception->getMessage(), 0, 500)],
        ])->save();
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

        // Geteilter Renderer: Mail-Anhang = exakt das Dokument des Downloads
        // (gleiche Vorlage + Rechtsangaben).
        $bytes = app(InvoicePdfRenderer::class)->output($this->invoice);

        // Vollaudit 2026-07 (M26): Dateihash am Zustellnachweis — wie beim
        // Download-Kanal, berechnet über exakt die versendeten Bytes.
        if ($this->dispatchId !== null) {
            \App\Models\InvoiceDispatch::query()->withoutGlobalScopes()
                ->whereKey($this->dispatchId)
                ->whereNull('sha256')
                ->update(['sha256' => \CommonToolkit\Helper\Data\CryptoHelper::hash($bytes)]);
        }

        $prefix = $this->invoice->isCreditNote() ? 'gutschrift' : 'rechnung';
        $filename = sprintf('%s-%s.pdf', $prefix, $this->invoice->number);

        return [
            Attachment::fromData(static fn(): string => $bytes, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
