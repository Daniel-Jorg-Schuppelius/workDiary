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

namespace App\Services\Billing\Mail;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Billing\DeliveryNote;
use App\Models\Customer\Customer;
use App\Services\Billing\DeliveryNotePdfRenderer;
use App\Services\Document\Contracts\MailableDocumentProvider;
use Illuminate\Database\Eloquent\Model;

/** Lieferschein im Belegversand (Beleg seit Phase 145, MVP-1163). */
final class DeliveryNoteMailDocument implements MailableDocumentProvider {
    public function __construct(private readonly DeliveryNotePdfRenderer $renderer) {}

    public function kinds(): array {
        return [RenderDocumentKind::DeliveryNote];
    }

    public function modelClass(RenderDocumentKind $kind): string {
        return DeliveryNote::class;
    }

    public function pdfBytes(Model $document, RenderDocumentKind $kind): string {
        /** @var DeliveryNote $document */
        return $this->renderer->render($document);
    }

    public function attachmentFilename(Model $document, RenderDocumentKind $kind): string {
        /** @var DeliveryNote $document */
        return $this->renderer->filename($document);
    }

    public function documentNumber(Model $document, RenderDocumentKind $kind): string {
        /** @var DeliveryNote $document */
        return $document->number;
    }

    public function variables(Model $document, RenderDocumentKind $kind): array {
        /** @var DeliveryNote $document */
        $document->loadMissing('customer');

        return [
            'customer_name' => (string) ($document->customer->name ?? ''),
            'customer_email' => (string) ($document->customer->email ?? ''),
            'document_number' => $document->number,
            'document_date' => ($document->delivered_on ?? $document->created_at)?->format('d.m.Y') ?? '',
        ];
    }

    public function defaultRecipient(Model $document, RenderDocumentKind $kind): string {
        /** @var DeliveryNote $document */
        $customer = $document->customer;

        return (string) ($customer?->primaryContact()['email'] ?? $customer->email ?? '');
    }

    public function localeCustomer(Model $document, RenderDocumentKind $kind): ?Customer {
        /** @var DeliveryNote $document */
        return $document->customer;
    }

    public function afterSent(Model $document, RenderDocumentKind $kind): void {}
}
