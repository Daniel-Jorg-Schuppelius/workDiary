<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Construction\ConstructionNotice;
use App\Models\{DocumentDispatch, PurchaseOrder, Quote, StockDelivery};
use App\Services\Document\DocumentMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope, Headers};
use Illuminate\Queue\SerializesModels;

/**
 * Generischer Belegversand (Feature 128, MVP-692): Angebot, AB, Bestellung,
 * Lieferschein oder VOB/B-Schreiben (MVP-728) als E-Mail mit PDF-Anhang.
 *
 * - Subject + Bodies kommen fertig gerendert aus dem
 *   {@see \App\Models\InvoiceMailTemplate} (XSS-sicher, kein Blade in DB).
 * - Empfänger setzt der {@see DocumentMailService} via ->to()/->cc()/->bcc().
 * - PDF entsteht in attachments() on-the-fly über exakt den Renderer des
 *   Downloads (kein großer Anhang in der Queue-Payload).
 * - Zustellnachweis wie beim Rechnungsversand: Header X-WorkDiary-Dispatch →
 *   {@see \App\Listeners\RecordInvoiceMailDelivery} schreibt queued→sent.
 */
class DocumentMail extends Mailable implements ShouldQueue {
    use Queueable, SerializesModels;

    public function __construct(
        public Quote|PurchaseOrder|StockDelivery|ConstructionNotice $document,
        public string $documentKind,
        public string $renderedSubject,
        public string $renderedHtml,
        public string $renderedText,
        public ?int $dispatchId = null,
    ) {
        // Belegsprache je Kunde (Feature 034, MVP-721); Bestellungen haben
        // keinen Kunden und bleiben bei der Sprache der Organisation/Anzeige.
        $this->locale(\App\Support\DocumentLocale::for($document instanceof PurchaseOrder ? null : $document->customer));
    }

    public function envelope(): Envelope {
        return new Envelope(subject: $this->renderedSubject);
    }

    public function headers(): Headers {
        return new Headers(text: array_filter([
            \App\Listeners\RecordInvoiceMailDelivery::HEADER => $this->dispatchId !== null ? (string) $this->dispatchId : null,
        ]));
    }

    /** Queue-Fehlschlag → Zustellnachweis auf failed (wie InvoiceMail). */
    public function failed(\Throwable $exception): void {
        if ($this->dispatchId === null) {
            return;
        }
        $dispatch = DocumentDispatch::query()->withoutGlobalScopes()->find($this->dispatchId);
        $dispatch?->forceFill([
            'status' => 'failed',
            'meta' => [...(array) $dispatch->meta, 'error' => mb_substr($exception->getMessage(), 0, 500)],
        ])->save();
    }

    public function content(): Content {
        return new Content(
            view: 'mail.document',
            text: 'mail.document-text',
            with: [
                'html' => $this->renderedHtml,
                'text' => $this->renderedText,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array {
        $kind = RenderDocumentKind::from($this->documentKind);
        $service = app(DocumentMailService::class);
        $bytes = $service->pdfBytes($this->document, $kind);
        $filename = $service->attachmentFilename($this->document, $kind);

        // Dateihash am Zustellnachweis — berechnet über exakt die
        // versendeten Bytes (Vollaudit 2026-07, M26).
        if ($this->dispatchId !== null) {
            DocumentDispatch::query()->withoutGlobalScopes()
                ->whereKey($this->dispatchId)
                ->whereNull('sha256')
                ->update(['sha256' => \CommonToolkit\Helper\Data\CryptoHelper::hash($bytes)]);
        }

        return [
            Attachment::fromData(static fn (): string => $bytes, $filename)->withMime('application/pdf'),
        ];
    }
}
