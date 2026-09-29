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

use App\Services\Billing\Dto\ExternalProductRevenue;
use Carbon\CarbonInterface;

/**
 * Umsatz aus allen angebundenen Buchhaltungsprogrammen zusammen (MVP-1034).
 * Als Contract, damit auch die Plattform (Navigation) ihn nutzen kann; die
 * Quellen tragen sich über {@see \App\Services\Billing\ExternalRevenueSources} ein.
 */
interface ExternalRevenue {
    /**
     * @param  list<int>  $excludedCustomerIds
     * @return list<array{id:?int, customerId:int, number:string, issuedOn:?string, dueOn:?string, paidOn:?string, total:float, paid:bool}>
     */
    public function invoiceRows(string $upTo, ?int $customerId = null, array $excludedCustomerIds = []): array;

    /**
     * @param  list<int>  $excludedCustomerIds
     * @return array<int, array{count:int, total:float}>
     */
    public function perCustomer(string $from, string $to, ?int $customerId = null, array $excludedCustomerIds = []): array;

    /** @return array<string, float> `Y-m` → Betrag */
    public function monthlyRevenue(int $customerId, CarbonInterface $from, CarbonInterface $to): array;

    public function overdueCount(int $organizationId, string $today): int;

    /** @return array<string, list<ExternalProductRevenue>> Quelle → Artikelzeilen */
    public function productRevenue(CarbonInterface $from, CarbonInterface $to): array;
}
