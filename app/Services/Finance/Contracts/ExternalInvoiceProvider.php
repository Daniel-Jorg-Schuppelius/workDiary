<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Contracts;

use App\Models\Finance\BillingTransfer;
use App\Services\Finance\Dto\{ExternalInvoiceFile, ExternalInvoiceSnapshot};

/**
 * Erweiterungspunkt (Feature 196, Nachtrag 07.11.2026): ein Fakturasystem mit
 * Rechnungshoheit liefert Stand und Original seiner Rechnungen, damit WorkDiary
 * sie mit Nachweisen ausstatten und zustellen kann. Plugins tragen sich über
 * `ModuleRegistry::contribute(ExternalInvoiceProvider::class, …)` ein; Zuordnung,
 * Ergänzung, Prüfung und Versand führt der Kern.
 *
 * Ein Anbieter, der etwas nicht kann, liefert null statt zu raten; der Kern
 * macht daraus einen konkreten Sperrgrund.
 */
interface ExternalInvoiceProvider {
    /** Plugin-ID, zugleich Wert von `external_invoices.plugin_id` und `TransferTarget`. */
    public function key(): string;

    public function label(): string;

    public function isConfigured(?int $organizationId): bool;

    /** Rechnungs-ID beim Anbieter zu einem Übergabenachweis; null, solange es dort keine Rechnung gibt. */
    public function invoiceIdFor(BillingTransfer $transfer): ?string;

    /** Aktueller Stand; wirft bei Netz- oder API-Fehlern. */
    public function fetch(int $organizationId, string $externalId): ExternalInvoiceSnapshot;

    /**
     * Original einer festgeschriebenen Rechnung im ausgestellten Format; null, wenn der Anbieter keines liefert.
     * `$preferXml`: liefert der Anbieter dieselbe E-Rechnung als XML und als ZUGFeRD-PDF, die XML wählen
     * (Empfänger, die per Mail nur XML annehmen).
     */
    public function original(int $organizationId, ExternalInvoiceSnapshot $snapshot, bool $preferXml = false): ?ExternalInvoiceFile;

    /**
     * Rechnungen des Kunden aus dem Belegspiegel, für die direkte Zuordnung extern angelegter Rechnungen.
     *
     * @return list<array{external_id: string, number: string|null, date: string|null}>
     */
    public function candidates(int $organizationId, int $customerId): array;
}
