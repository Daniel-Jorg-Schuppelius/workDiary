<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalRevenue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Invoicing\Invoice;
use App\Services\Billing\Dto\{ExternalInvoice, ExternalInvoiceFile, ExternalInvoiceQuery, ExternalProductRevenue};
use Carbon\CarbonInterface;

/**
 * Umsatz aus allen angebundenen Buchhaltungsprogrammen zusammen (MVP-1034).
 * Als Contract, damit auch die Plattform (Navigation) ihn nutzen kann; die
 * Quellen tragen sich über {@see \App\Services\Billing\ExternalRevenueSources} ein.
 */
interface ExternalRevenue {
    /** @return list<ExternalInvoice> */
    public function invoices(ExternalInvoiceQuery $query): array;

    /**
     * Umsatz je zugeordnetem Kunden im Zeitraum, Gutschriften mindern.
     *
     * @param  list<int>  $excludedCustomerIds
     * @return array<int, array{count:int, total:float}>
     */
    public function perCustomer(string $from, string $to, ?int $customerId = null, array $excludedCustomerIds = []): array;

    /** @return array<string, float> `Y-m` → Betrag, Gutschriften mindern */
    public function monthlyRevenue(int $customerId, CarbonInterface $from, CarbonInterface $to): array;

    /** Offene Rechnungen und Abschläge mit Fälligkeit vor `$today`. */
    public function overdueCount(int $organizationId, string $today): int;

    /** @return array<string, list<ExternalProductRevenue>> Quelle → Artikelzeilen */
    public function productRevenue(CarbonInterface $from, CarbonInterface $to): array;

    /** @return list<int> Kunden mit gespiegelten Belegen */
    public function customerIdsWithDocuments(int $organizationId): array;

    public function file(string $source, int $organizationId, string $key): ?ExternalInvoiceFile;

    public function fileForInvoice(Invoice $invoice): ?ExternalInvoiceFile;
}
