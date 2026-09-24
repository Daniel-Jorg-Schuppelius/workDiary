<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteMailDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Mail;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Customer\Customer;
use App\Models\Sales\Quote;
use App\Services\Document\Contracts\MailableDocumentProvider;
use App\Services\Invoicing\{OrderConfirmationPdfRenderer, QuotePdfRenderer};
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;

/** Angebot und Auftragsbestätigung im Belegversand. */
final class QuoteMailDocument implements MailableDocumentProvider {
    public function kinds(): array {
        return [RenderDocumentKind::Quote, RenderDocumentKind::OrderConfirmation];
    }

    public function modelClass(RenderDocumentKind $kind): string {
        return Quote::class;
    }

    public function pdfBytes(Model $document, RenderDocumentKind $kind): string {
        /** @var Quote $document */
        return $kind === RenderDocumentKind::OrderConfirmation
            ? app(OrderConfirmationPdfRenderer::class)->output($document)
            : app(QuotePdfRenderer::class)->output($document);
    }

    public function attachmentFilename(Model $document, RenderDocumentKind $kind): string {
        /** @var Quote $document */
        $number = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $document->number);

        return $kind === RenderDocumentKind::OrderConfirmation
            ? sprintf('auftragsbestaetigung-%s.pdf', $number)
            : sprintf('angebot-%s-v%d.pdf', $number, $document->version);
    }

    public function documentNumber(Model $document, RenderDocumentKind $kind): string {
        /** @var Quote $document */
        return (string) $document->number;
    }

    public function variables(Model $document, RenderDocumentKind $kind): array {
        /** @var Quote $document */
        $document->loadMissing('customer');

        return [
            'customer_name' => (string) ($document->customer->name ?? ''),
            'customer_email' => (string) ($document->customer->email ?? ''),
            'document_number' => (string) $document->number,
            'document_date' => optional($document->created_at)->format('d.m.Y') ?? '',
            'valid_until' => optional($document->valid_until)->format('d.m.Y') ?? '',
            'total' => DocumentNumber::decimal($document->total?->toFloat() ?? 0.0, 2),
            'currency' => $document->total?->getCurrency()->value ?? 'EUR',
        ];
    }

    public function defaultRecipient(Model $document, RenderDocumentKind $kind): string {
        /** @var Quote $document */
        $customer = $document->customer;

        return (string) ($customer?->primaryContact()['email'] ?? $customer->email ?? '');
    }

    public function localeCustomer(Model $document, RenderDocumentKind $kind): ?Customer {
        /** @var Quote $document */
        return $document->customer;
    }

    public function afterSent(Model $document, RenderDocumentKind $kind): void {}
}
