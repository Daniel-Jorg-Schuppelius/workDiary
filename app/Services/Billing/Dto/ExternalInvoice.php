<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Invoicing\Invoice;
use Carbon\CarbonImmutable;

/**
 * Umsatzbeleg aus dem Buchhaltungsprogramm (MVP-1115) in der Sprache der
 * lokalen Rechnung: `type` aus {@see Invoice::TYPES}
 * (Rechnung, Abschlag, Gutschrift), `status` gestellt/teilweise/bezahlt.
 * Beträge tragen das Vorzeichen der Belegart — Gutschriften sind negativ.
 * Ohne zugeordneten Kunden ist `customerId` null; `contactName` nennt dann
 * den Kontakt im Buchhaltungsprogramm.
 */
final readonly class ExternalInvoice {
    public function __construct(
        public string $source,
        public string $key,
        public string $number,
        public ?int $customerId,
        public ?string $contactName,
        public string $type,
        public InvoiceStatus $status,
        public ?CarbonImmutable $issuedOn,
        public ?CarbonImmutable $dueOn,
        public ?CarbonImmutable $paidOn,
        public float $gross,
        public ?float $net,
        public float $open,
        public string $currency,
        public ?string $url,
    ) {}

    public function isCreditNote(): bool {
        return $this->type === Invoice::TYPE_CREDIT_NOTE;
    }
}
