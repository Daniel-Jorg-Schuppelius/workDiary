<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMailService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Mail\DocumentMail;
use App\Models\Construction\ConstructionNotice;
use App\Models\{DocumentDispatch, InvoiceMailTemplate, PurchaseOrder, Quote, StockDelivery};
use App\Services\Construction\ConstructionNoticePdfRenderer;
use App\Services\Invoicing\{OrderConfirmationPdfRenderer, QuotePdfRenderer};
use App\Services\Manufacturing\DeliveryNotePdfRenderer;
use App\Services\Procurement\PurchaseOrderPdfRenderer;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\{Auth, Mail};
use InvalidArgumentException;

/**
 * Generischer Belegversand per E-Mail (Feature 128, MVP-692): EIN Weg für
 * Angebot, Auftragsbestätigung, Bestellung, Lieferschein und die VOB/B-
 * Schreiben (Feature 062, MVP-728) — Vorlage
 * auflösen, Platzhalter rendern, PDF als Anhang queuen, Zustellversuch
 * in document_dispatches protokollieren, Audit `{kind}.mailed` am Beleg.
 *
 * Die Rechnung behält bewusst ihren eigenen Versandpfad
 * ({@see \App\Http\Controllers\InvoiceController::send()}): E-Rechnungs-
 * Formate, Ausstellungs-Preflight und markSent() gehören zur
 * Rechnungs-Domäne — beide Pfade schreiben aber dasselbe Dispatch-Log.
 */
class DocumentMailService {
    /**
     * Belegart → Modellklasse des Versandobjekts.
     *
     * @var array<string, class-string>
     */
    private const DOCUMENT_MODELS = [
        'quote' => Quote::class,
        'order_confirmation' => Quote::class,
        'purchase_order' => PurchaseOrder::class,
        'delivery_note' => StockDelivery::class,
        // VOB/B-Schreiben (Feature 062, MVP-728): foermliche Anzeigen an den
        // Auftraggeber — der Zugangsnachweis ist der Zweck des Versands.
        'construction_obstruction_notice' => ConstructionNotice::class,
        'construction_concern_notice' => ConstructionNotice::class,
    ];

    /** Vom generischen Versand unterstützte Belegarten. */
    public static function supports(RenderDocumentKind $kind): bool {
        return isset(self::DOCUMENT_MODELS[$kind->value]);
    }

    /**
     * Versendet den Beleg als PDF-Mail (queued) und protokolliert den
     * Zustellversuch. Empfänger sind vorvalidiert (Controller).
     *
     * @param  array{to: list<string>, cc?: list<string>, bcc?: list<string>}  $recipients
     */
    public function send(
        Quote|PurchaseOrder|StockDelivery|ConstructionNotice $document,
        RenderDocumentKind $kind,
        array $recipients,
        ?InvoiceMailTemplate $template = null,
        ?string $customText = null,
        bool $bccSender = false,
    ): DocumentDispatch {
        $this->assertSendable($document, $kind);

        $organizationId = (int) $document->organization_id;
        $template ??= InvoiceMailTemplate::defaultFor($organizationId, $kind);
        // Belegsprache je Kunde (Feature 034, MVP-721): Platzhalter wie die
        // Belegart-Bezeichnung in der Sprache des Empfängers.
        $rendered = \App\Support\DocumentLocale::within(
            $document instanceof PurchaseOrder ? null : $document->customer,
            null,
            fn (): array => $template->render($this->variablesFor($document, $kind, $customText)),
        );

        $bcc = $recipients['bcc'] ?? [];
        if ($bccSender) {
            $senderAddr = (string) config('mail.from.address');
            if ($senderAddr !== '' && ! in_array($senderAddr, $bcc, true)) {
                $bcc[] = $senderAddr;
            }
        }

        // Dispatch VOR dem Queuen (Vollaudit 2026-07, M26) — Status,
        // Message-ID und Dateihash schreibt der Versandpfad nach.
        $dispatch = DocumentDispatch::query()->create([
            'organization_id' => $organizationId,
            'document_kind' => $kind->value,
            'document_id' => (int) $document->getKey(),
            'channel' => DocumentDispatch::CHANNEL_EMAIL,
            'format' => 'pdf',
            'status' => 'queued',
            'recipient' => implode(', ', $recipients['to']),
            'meta' => array_filter([
                'cc' => $recipients['cc'] ?? [],
                'template_id' => $template->id,
            ]),
            'created_by' => Auth::id(),
        ]);

        $mail = new DocumentMail($document, $kind->value, $rendered['subject'], $rendered['html'], $rendered['text'], (int) $dispatch->id);
        $pending = Mail::to($recipients['to']);
        if (! empty($recipients['cc'])) {
            $pending->cc($recipients['cc']);
        }
        if ($bcc !== []) {
            $pending->bcc($bcc);
        }
        $pending->queue($mail);

        $document->audit($kind->value . '.mailed', [
            'to' => $recipients['to'],
            'dispatch_id' => $dispatch->id,
            'template_id' => $template->id,
        ]);

        return $dispatch;
    }

    /** PDF-Bytes des Belegs — exakt der Renderer des Downloads. */
    public function pdfBytes(Quote|PurchaseOrder|StockDelivery|ConstructionNotice $document, RenderDocumentKind $kind): string {
        $this->assertSendable($document, $kind);

        return match (true) {
            $kind === RenderDocumentKind::Quote && $document instanceof Quote => app(QuotePdfRenderer::class)->output($document),
            $kind === RenderDocumentKind::OrderConfirmation && $document instanceof Quote => app(OrderConfirmationPdfRenderer::class)->output($document),
            $document instanceof PurchaseOrder => app(PurchaseOrderPdfRenderer::class)->render($document),
            $document instanceof StockDelivery => app(DeliveryNotePdfRenderer::class)->render($document),
            $document instanceof ConstructionNotice => app(ConstructionNoticePdfRenderer::class)->render($document),
            default => throw new InvalidArgumentException('Belegart ohne generischen Versand: ' . $kind->value),
        };
    }

    /** Anhang-Dateiname — deckungsgleich mit dem jeweiligen Download. */
    public function attachmentFilename(Quote|PurchaseOrder|StockDelivery|ConstructionNotice $document, RenderDocumentKind $kind): string {
        return match (true) {
            $kind === RenderDocumentKind::Quote && $document instanceof Quote => sprintf('angebot-%s-v%d.pdf', $this->safe((string) $document->number), $document->version),
            $kind === RenderDocumentKind::OrderConfirmation && $document instanceof Quote => sprintf('auftragsbestaetigung-%s.pdf', $this->safe((string) $document->number)),
            $document instanceof PurchaseOrder => app(PurchaseOrderPdfRenderer::class)->filename($document) . '.pdf',
            $document instanceof StockDelivery => app(DeliveryNotePdfRenderer::class)->number($document) . '.pdf',
            $document instanceof ConstructionNotice => app(ConstructionNoticePdfRenderer::class)->filename($document) . '.pdf',
            default => throw new InvalidArgumentException('Belegart ohne generischen Versand: ' . $kind->value),
        };
    }

    /**
     * Platzhalter-Werte der Belegart ({@see InvoiceMailTemplate::availableVariables()}).
     *
     * @return array<string, string>
     */
    public function variablesFor(Quote|PurchaseOrder|StockDelivery|ConstructionNotice $document, RenderDocumentKind $kind, ?string $customText = null): array {
        $companyName = (string) (config('branding.app_name') ?: config('app.name', 'workDiary'));
        $common = [
            'company_name' => $companyName,
            'document_label' => $kind->label(),
            'custom_text' => (string) ($customText ?? ''),
        ];

        if ($document instanceof Quote) {
            $document->loadMissing('customer');

            return $common + [
                'customer_name' => (string) ($document->customer->name ?? ''),
                'customer_email' => (string) ($document->customer->email ?? ''),
                'document_number' => (string) $document->number,
                'document_date' => optional($document->created_at)->format('d.m.Y') ?? '',
                'valid_until' => optional($document->valid_until)->format('d.m.Y') ?? '',
                'total' => DocumentNumber::decimal($document->total?->toFloat() ?? 0.0, 2),
                'currency' => $document->total?->getCurrency()->value ?? 'EUR',
            ];
        }

        if ($document instanceof ConstructionNotice) {
            $document->loadMissing(['customer', 'project', 'site']);

            return $common + [
                'customer_name' => (string) ($document->recipient_name ?: ($document->customer->name ?? '')),
                'customer_email' => (string) ($document->recipient_email ?: ($document->customer->email ?? '')),
                'document_number' => $document->displayNo(),
                'document_date' => $document->occurred_on->format('d.m.Y'),
                'document_subject' => (string) $document->subject,
                'project_name' => (string) ($document->project->name ?? $document->site->name ?? ''),
                'legal_reference' => (string) ($document->legal_reference ?? ''),
            ];
        }

        if ($document instanceof PurchaseOrder) {
            $document->loadMissing('supplier');

            return $common + [
                'supplier_name' => (string) ($document->supplier->name ?? ''),
                'supplier_email' => (string) ($document->supplier->email ?? ''),
                'document_number' => (string) $document->number,
                'document_date' => optional($document->ordered_at ?? $document->created_at)->format('d.m.Y') ?? '',
                'currency' => $document->currency->value,
            ];
        }

        $document->loadMissing('customer');

        return $common + [
            'customer_name' => (string) ($document->customer->name ?? ''),
            'customer_email' => (string) ($document->customer->email ?? ''),
            'document_number' => app(DeliveryNotePdfRenderer::class)->number($document),
            'document_date' => optional($document->delivered_at ?? $document->created_at)->format('d.m.Y') ?? '',
        ];
    }

    /** Empfänger-Vorbelegung: primäre E-Mail von Kunde bzw. Lieferant. */
    public function defaultRecipient(Quote|PurchaseOrder|StockDelivery|ConstructionNotice $document, RenderDocumentKind $kind): string {
        if ($document instanceof ConstructionNotice) {
            return (string) ($document->recipient_email ?: ($document->customer->email ?? ''));
        }

        if ($document instanceof PurchaseOrder) {
            $supplier = $document->supplier;

            return (string) ($supplier?->primaryContact()['email'] ?? $supplier->email ?? '');
        }

        $customer = $document->customer;

        return (string) ($customer?->primaryContact()['email'] ?? $customer->email ?? '');
    }

    /** Belegart unterstützt + Modellklasse passt — sonst InvalidArgumentException. */
    public function assertSendable(Quote|PurchaseOrder|StockDelivery|ConstructionNotice $document, RenderDocumentKind $kind): void {
        $expected = self::DOCUMENT_MODELS[$kind->value] ?? null;
        if ($expected === null || $document::class !== $expected) {
            throw new InvalidArgumentException(sprintf(
                'Belegart %s erwartet %s, %s übergeben.',
                $kind->value,
                (string) $expected,
                $document::class,
            ));
        }
    }

    private function safe(string $value): string {
        return (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $value);
    }
}
