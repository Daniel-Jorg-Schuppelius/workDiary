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

use App\Models\Invoicing\Invoice;
use App\Services\Billing\Dto\{ExternalInvoice, ExternalInvoiceFile, ExternalInvoiceQuery, ExternalProductRevenue};
use Carbon\CarbonInterface;

/**
 * Umsatzbelege, die nur im Buchhaltungsprogramm existieren (MVP-1034):
 * Bei externer Rechnungshoheit liefert das Plugin sie für Auswertungen nach.
 * Belege, die aus einer lokalen Rechnung übergeben wurden, zählt die Quelle
 * nicht mit — keine Doppelzählung mit der lokalen Fakturierung.
 *
 * Seit MVP-1114 liefert die Quelle ihre Belege über EINE Methode
 * ({@see invoices()}); Summen je Kunde, je Monat und Überfälliges leitet
 * {@see \App\Services\Billing\ExternalRevenueSources} daraus ab. So rechnen alle
 * Auswertungen nach denselben Beleg- und Statusregeln.
 */
interface ExternalRevenueSource {
    /**
     * Schlüssel der Quelle im Register. Die Herkunft je Beleg (Plugin-ID) steht
     * in {@see ExternalInvoice::$source} — eine Quelle kann mehrere Programme
     * führen (allgemeiner Belegspiegel, MVP-1127).
     */
    public function key(): string;

    /**
     * Gestellte und (teil)bezahlte Rechnungen, Abschläge und Gutschriften —
     * ohne Entwürfe, Stornos und aus lokalen Rechnungen übergebene Belege.
     *
     * @return list<ExternalInvoice>
     */
    public function invoices(ExternalInvoiceQuery $query): array;

    /** @return list<ExternalProductRevenue> Umsatz je Artikel im Zeitraum */
    public function productRevenue(CarbonInterface $from, CarbonInterface $to): array;

    /**
     * Kunden mit gespiegelten Belegen jeder Art — sie haben Belege, auch ohne
     * lokale Rechnung (Löschprüfung, MVP-1114).
     *
     * @return list<int>
     */
    public function customerIdsWithDocuments(int $organizationId): array;

    /** Belegdatei zu {@see ExternalInvoice::$source}/`$key`; null, wenn die Quelle sie nicht führt (MVP-1118). */
    public function file(int $organizationId, string $source, string $key): ?ExternalInvoiceFile;

    /** Belegdatei einer an das Programm übergebenen lokalen Rechnung; null, wenn nicht übergeben. */
    public function fileForInvoice(Invoice $invoice): ?ExternalInvoiceFile;
}
