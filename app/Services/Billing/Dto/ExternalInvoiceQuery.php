<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceQuery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use Carbon\CarbonInterface;

/**
 * Filter für {@see \App\Services\Billing\Contracts\ExternalRevenue::invoices()}.
 * Ausgeschlossene Kunden lassen Belege ohne Kunden stehen. Ohne angemeldete
 * Organisation (Scheduler, Konsole) muss `organizationId` gesetzt sein.
 */
final readonly class ExternalInvoiceQuery {
    /** @param list<int> $excludedCustomerIds */
    public function __construct(
        public ?CarbonInterface $issuedFrom = null,
        public ?CarbonInterface $issuedTo = null,
        public ?int $customerId = null,
        public array $excludedCustomerIds = [],
        public bool $withCreditNotes = true,
        public bool $openOnly = false,
        public ?int $organizationId = null,
    ) {}
}
