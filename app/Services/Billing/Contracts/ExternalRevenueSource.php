<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalRevenueSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Services\Billing\Dto\ExternalProductRevenue;
use Carbon\CarbonInterface;

/**
 * Umsatzbelege, die nur im Buchhaltungsprogramm existieren (MVP-1034):
 * Bei externer Rechnungshoheit liefert das Plugin sie für Auswertungen nach.
 * Belege, die aus einer lokalen Rechnung übergeben wurden, zählt die Quelle
 * nicht mit — keine Doppelzählung mit der lokalen Fakturierung.
 */
interface ExternalRevenueSource {
    /** Plugin-ID; zugleich Herkunftskennung in Berichten. */
    public function key(): string;

    /**
     * Rechnungsähnliche Belege für den Zahlungsverhaltens-Report, gleiche
     * Zeilenform wie lokale Rechnungen.
     *
     * @param  list<int>  $excludedCustomerIds
     * @return list<array{id:?int, customerId:int, number:string, issuedOn:?string, dueOn:?string, paidOn:?string, total:float, paid:bool}>
     */
    public function invoiceRows(string $upTo, ?int $customerId = null, array $excludedCustomerIds = []): array;

    /**
     * Umsatz je Kunde im Zeitraum (Gutschriften mindern).
     *
     * @param  list<int>  $excludedCustomerIds
     * @return array<int, array{count:int, total:float}> Kunden-ID → Aggregat
     */
    public function perCustomer(string $from, string $to, ?int $customerId = null, array $excludedCustomerIds = []): array;

    /**
     * Fakturierter Umsatz eines Kunden je Monat.
     *
     * @return array<string, float> `Y-m` → Betrag
     */
    public function monthlyRevenue(int $customerId, CarbonInterface $from, CarbonInterface $to): array;

    /** Offene, überfällige Umsatzbelege der Organisation. */
    public function overdueCount(int $organizationId, string $today): int;

    /** @return list<ExternalProductRevenue> Umsatz je Artikel im Zeitraum */
    public function productRevenue(CarbonInterface $from, CarbonInterface $to): array;
}
