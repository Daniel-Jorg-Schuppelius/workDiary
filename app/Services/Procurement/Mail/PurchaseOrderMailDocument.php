<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderMailDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Mail;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Customer\Customer;
use App\Models\Procurement\PurchaseOrder;
use App\Services\Document\Contracts\MailableDocumentProvider;
use App\Services\Procurement\PurchaseOrderPdfRenderer;
use Illuminate\Database\Eloquent\Model;

/** Bestellung an den Lieferanten im Belegversand. */
final class PurchaseOrderMailDocument implements MailableDocumentProvider {
    public function __construct(private readonly PurchaseOrderPdfRenderer $renderer) {}

    public function kinds(): array {
        return [RenderDocumentKind::PurchaseOrder];
    }

    public function modelClass(RenderDocumentKind $kind): string {
        return PurchaseOrder::class;
    }

    public function pdfBytes(Model $document, RenderDocumentKind $kind): string {
        /** @var PurchaseOrder $document */
        return $this->renderer->render($document);
    }

    public function attachmentFilename(Model $document, RenderDocumentKind $kind): string {
        /** @var PurchaseOrder $document */
        return $this->renderer->filename($document) . '.pdf';
    }

    public function documentNumber(Model $document, RenderDocumentKind $kind): string {
        /** @var PurchaseOrder $document */
        return (string) $document->number;
    }

    public function variables(Model $document, RenderDocumentKind $kind): array {
        /** @var PurchaseOrder $document */
        $document->loadMissing('supplier');

        return [
            'supplier_name' => (string) ($document->supplier->name ?? ''),
            'supplier_email' => (string) ($document->supplier->email ?? ''),
            'document_number' => (string) $document->number,
            'document_date' => optional($document->ordered_at ?? $document->created_at)->format('d.m.Y') ?? '',
            'currency' => $document->currency->value,
        ];
    }

    public function defaultRecipient(Model $document, RenderDocumentKind $kind): string {
        /** @var PurchaseOrder $document */
        $supplier = $document->supplier;

        return (string) ($supplier?->primaryContact()['email'] ?? $supplier->email ?? '');
    }

    /** Lieferantenbelege kennen keine Kundensprache. */
    public function localeCustomer(Model $document, RenderDocumentKind $kind): ?Customer {
        return null;
    }

    public function afterSent(Model $document, RenderDocumentKind $kind): void {}
}
