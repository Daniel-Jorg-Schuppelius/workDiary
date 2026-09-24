<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNoteMailDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing\Mail;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Customer\Customer;
use App\Models\Inventory\StockDelivery;
use App\Services\Document\Contracts\MailableDocumentProvider;
use App\Services\Manufacturing\DeliveryNotePdfRenderer;
use Illuminate\Database\Eloquent\Model;

/** Lieferschein der Auslieferung im Belegversand. */
final class DeliveryNoteMailDocument implements MailableDocumentProvider {
    public function __construct(private readonly DeliveryNotePdfRenderer $renderer) {}

    public function kinds(): array {
        return [RenderDocumentKind::DeliveryNote];
    }

    public function modelClass(RenderDocumentKind $kind): string {
        return StockDelivery::class;
    }

    public function pdfBytes(Model $document, RenderDocumentKind $kind): string {
        /** @var StockDelivery $document */
        return $this->renderer->render($document);
    }

    public function attachmentFilename(Model $document, RenderDocumentKind $kind): string {
        /** @var StockDelivery $document */
        return $this->renderer->number($document) . '.pdf';
    }

    public function documentNumber(Model $document, RenderDocumentKind $kind): string {
        /** @var StockDelivery $document */
        return $this->renderer->number($document);
    }

    public function variables(Model $document, RenderDocumentKind $kind): array {
        /** @var StockDelivery $document */
        $document->loadMissing('customer');

        return [
            'customer_name' => (string) ($document->customer->name ?? ''),
            'customer_email' => (string) ($document->customer->email ?? ''),
            'document_number' => $this->renderer->number($document),
            'document_date' => optional($document->delivered_at ?? $document->created_at)->format('d.m.Y') ?? '',
        ];
    }

    public function defaultRecipient(Model $document, RenderDocumentKind $kind): string {
        /** @var StockDelivery $document */
        $customer = $document->customer;

        return (string) ($customer?->primaryContact()['email'] ?? $customer->email ?? '');
    }

    public function localeCustomer(Model $document, RenderDocumentKind $kind): ?Customer {
        /** @var StockDelivery $document */
        return $document->customer;
    }

    public function afterSent(Model $document, RenderDocumentKind $kind): void {}
}
