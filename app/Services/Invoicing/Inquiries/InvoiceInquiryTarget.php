<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Inquiries;

use App\Models\Invoicing\Invoice;
use App\Services\Billing\Dto\ExternalInvoice;

/**
 * Ziel einer Rechnungsnachfrage (MVP-1200): eine lokale Rechnung oder ein
 * Beleg des Buchhaltungsprogramms aus dem Belegspiegel — nie beides.
 */
final readonly class InvoiceInquiryTarget {
    private function __construct(
        public ?Invoice $invoice,
        public ?ExternalInvoice $external,
        public string $number,
        public ?int $customerId,
    ) {}

    public static function local(Invoice $invoice): self {
        return new self($invoice, null, (string) ($invoice->number ?? $invoice->external_number ?? ''), (int) $invoice->customer_id > 0 ? (int) $invoice->customer_id : null);
    }

    public static function external(ExternalInvoice $invoice): self {
        return new self(null, $invoice, $invoice->number, $invoice->customerId);
    }

    public function isLocal(): bool {
        return $this->invoice !== null;
    }

    /** Plugin-ID des Programms, bei lokalen Rechnungen null. */
    public function source(): ?string {
        return $this->external?->source;
    }

    public function key(): ?string {
        return $this->external?->key;
    }

    /**
     * Snapshot-Form für den Inbox-Eintrag (ohne Modelle).
     *
     * @return array{number: string, source: ?string, key: ?string, customer_id: ?int, invoice_sqid: ?string}
     */
    public function toArray(): array {
        return [
            'number' => $this->number,
            'source' => $this->source(),
            'key' => $this->key(),
            'customer_id' => $this->customerId,
            'invoice_sqid' => $this->invoice?->getRouteKey(),
        ];
    }
}
