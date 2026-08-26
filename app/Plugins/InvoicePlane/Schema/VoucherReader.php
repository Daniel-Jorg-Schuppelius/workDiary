<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VoucherReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane\Schema;

/**
 * Nur-lesender Rechnungsabruf einer InvoicePlane-Instanz (Feature 086/122,
 * MVP-731 — Vollscan G18).
 *
 * InvoicePlane 1.x hat **keine offizielle REST-API** — die Anbindung dieses
 * Plugins ist deshalb von Anfang an eine schreibgeschützte Datenbanksicht
 * über die Allowlist aus `config/invoiceplane.php` (Feature 086, MVP-419).
 * Der Beleg-Pull macht daran nichts anders: Er liest, was der Adapter über
 * `ip_invoices` + `ip_invoice_amounts` + `ip_clients` liefert.
 *
 * Bewusst als Interface — wie {@see SchemaReader}: der reale Adapter hängt an
 * einer Pilotinstanz, die Abbildung auf `accounting_vouchers` ist ohne sie
 * testbar.
 *
 * Erwartete Spalten je Zeile (Namen wie im `ip_*`-Schema, ohne Präfix):
 * `invoice_id`, `client_id`, `client_name`, `invoice_number`,
 * `invoice_date_created`, `invoice_date_modified` (falls vorhanden),
 * `invoice_date_due`, `invoice_status_id`, `creditinvoice_parent_id`,
 * `invoice_total`, `invoice_item_subtotal`, `invoice_paid`, `invoice_balance`.
 */
interface VoucherReader {
    /**
     * Rechnungen ab einem Änderungsstand, seitenweise.
     *
     * @param  ?string  $sinceDate  `Y-m-d`; null = von vorn (Erstlauf).
     * @return list<array<string, mixed>>
     */
    public function invoicesSince(?string $sinceDate, int $offset, int $limit): array;
}
