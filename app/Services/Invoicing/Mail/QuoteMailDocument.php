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
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Services\Document\Contracts\MailableDocumentProvider;
use App\Services\Invoicing\{OrderConfirmationPdfRenderer, QuotePdfRenderer};
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;

/** Angebot und Auftragsbestätigung (eigener Beleg seit Phase 145, MVP-1162) im Belegversand. */
final class QuoteMailDocument implements MailableDocumentProvider {
    public function kinds(): array {
        return [RenderDocumentKind::Quote, RenderDocumentKind::OrderConfirmation];
    }

    public function modelClass(RenderDocumentKind $kind): string {
        return $kind === RenderDocumentKind::OrderConfirmation ? OrderConfirmation::class : Quote::class;
    }

    public function pdfBytes(Model $document, RenderDocumentKind $kind): string {
        return $document instanceof OrderConfirmation
            ? app(OrderConfirmationPdfRenderer::class)->output($document)
            : app(QuotePdfRenderer::class)->output($this->quote($document));
    }

    public function attachmentFilename(Model $document, RenderDocumentKind $kind): string {
        $number = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $document->getAttribute('number'));

        return $document instanceof OrderConfirmation
            ? sprintf('auftragsbestaetigung-%s.pdf', $number)
            : sprintf('angebot-%s-v%d.pdf', $number, $this->quote($document)->version);
    }

    public function documentNumber(Model $document, RenderDocumentKind $kind): string {
        return (string) $document->getAttribute('number');
    }

    public function variables(Model $document, RenderDocumentKind $kind): array {
        $document->loadMissing('customer');
        $total = $document instanceof OrderConfirmation ? $document->total : $this->quote($document)->total;
        $date = $document instanceof OrderConfirmation ? ($document->confirmed_on ?? $document->created_at) : $document->getAttribute('created_at');

        return [
            'customer_name' => (string) ($document->getRelation('customer')->name ?? ''),
            'customer_email' => (string) ($document->getRelation('customer')->email ?? ''),
            'document_number' => (string) $document->getAttribute('number'),
            'document_date' => optional($date)->format('d.m.Y') ?? '',
            'valid_until' => $document instanceof Quote ? (optional($document->valid_until)->format('d.m.Y') ?? '') : '',
            'total' => DocumentNumber::decimal($total?->toFloat() ?? 0.0, 2),
            'currency' => $total?->getCurrency()->value ?? 'EUR',
        ];
    }

    public function defaultRecipient(Model $document, RenderDocumentKind $kind): string {
        $customer = $this->customer($document);

        return (string) ($customer?->primaryContact()['email'] ?? $customer->email ?? '');
    }

    public function localeCustomer(Model $document, RenderDocumentKind $kind): ?Customer {
        return $this->customer($document);
    }

    public function afterSent(Model $document, RenderDocumentKind $kind): void {}

    private function quote(Model $document): Quote {
        return $document instanceof Quote ? $document : throw new \InvalidArgumentException('Quote expected, got ' . $document::class);
    }

    private function customer(Model $document): ?Customer {
        $customer = $document->getRelationValue('customer');

        return $customer instanceof Customer ? $customer : null;
    }
}
