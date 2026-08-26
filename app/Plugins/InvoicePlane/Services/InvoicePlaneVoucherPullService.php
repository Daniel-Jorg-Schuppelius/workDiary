<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePlaneVoucherPullService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane\Services;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Plugins\InvoicePlane\Schema\VoucherReaderFactory;
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, VoucherMirror, VoucherPuller};

/**
 * Beleg-Rückabruf aus InvoicePlane (Feature 086/122, MVP-731 — Vollscan G18).
 *
 * **Warum kein REST-Puller:** InvoicePlane 1.x veröffentlicht keine API;
 * v2 ist laut Versionsmatrix (`config/invoiceplane.php`) blockiert („keine
 * veröffentlichte Version/API"). Die Anbindung ist deshalb seit MVP-419 eine
 * schreibgeschützte Sicht auf das `ip_*`-Schema — der Beleg-Pull nutzt
 * dieselbe Naht ({@see VoucherReaderFactory}) statt eine API zu erfinden.
 *
 * - **Quelle**: `ip_invoices` + `ip_invoice_amounts` (+ `ip_clients` für den
 *   Namen) — genau die Tabellen, die die Read-Allowlist ohnehin freigibt.
 * - **Paginierung**: `offset`/`limit` auf der Leseseite (Query-Budget der
 *   Config: `invoiceplane.query.page_size`).
 * - **Inkrement**: `invoice_date_modified`, ersatzweise
 *   `invoice_date_created` — ab dem jüngsten bereits gespiegelten Stand.
 * - **Status** (`invoice_status_id`, InvoicePlane-Katalog): 1 = Entwurf,
 *   2 = versendet, 3 = gesehen, 4 = bezahlt, 5 = überfällig. „Versendet",
 *   „gesehen" und „überfällig" sind allesamt **offen** — überfällig ist eine
 *   Fristaussage, kein eigener Belegzustand.
 * - **Storno-Semantik**: InvoicePlane kennt keinen Storno-Status. Aufgehoben
 *   wird über eine **Gutschrift** (`creditinvoice_parent_id` zeigt auf die
 *   Ursprungsrechnung); zusätzlich gilt ein negativer Gesamtbetrag als
 *   Gutschrift. Beides wird als `credit_note` mit `cancels_external_id`
 *   gespiegelt — nicht als Cancellation: Die Ursprungsrechnung bleibt in
 *   InvoicePlane gültig.
 *
 * **Pilot-Vorbehalt:** Abgenommen ist das erst gegen eine echte Instanz —
 * Spaltenbestand und Statuskatalog variieren zwischen 1.5.x und 1.6.x.
 */
class InvoicePlaneVoucherPullService implements VoucherPuller {
    public const PLUGIN_ID = 'invoiceplane';

    /** InvoicePlane-Statuskatalog → normalisierter Belegzustand. */
    private const STATES = [
        1 => 'draft',
        2 => 'open',
        3 => 'open',
        4 => 'paid',
        5 => 'open',
    ];

    public function __construct(
        private readonly VoucherReaderFactory $readers,
        private readonly VoucherMirror $mirror,
    ) {}

    public function pluginId(): string {
        return self::PLUGIN_ID;
    }

    public function isConfigured(int $organizationId): bool {
        return $this->readers->for($organizationId) !== null;
    }

    /** @return array{read: int, created: int, updated: int, skipped: int} */
    public function pull(int $organizationId, int $pages = 2): array {
        $counters = VoucherMirror::counters();
        $reader = $this->readers->for($organizationId);
        if ($reader === null) {
            return $counters;
        }

        $limit = max(1, min(500, (int) config('invoiceplane.query.page_size', 500)));
        $since = $this->mirror->lastSourceChange($organizationId, $this->pluginId())?->toDateString();

        for ($page = 0; $page < max(1, $pages); $page++) {
            $rows = $reader->invoicesSince($since, $page * $limit, $limit);
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $counters['read']++;
                $this->mirror->store($organizationId, $this->pluginId(), $this->map($row), $counters);
            }

            if (count($rows) < $limit) {
                break;
            }
        }

        return $counters;
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): MirroredVoucher {
        $statusId = (int) ($row['invoice_status_id'] ?? 0);
        $parent = trim((string) ($row['creditinvoice_parent_id'] ?? ''));
        $parent = ($parent !== '' && $parent !== '0') ? $parent : null;
        $total = $row['invoice_total'] ?? null;
        $isCredit = $parent !== null || (is_numeric($total) && (float) $total < 0.0);

        return new MirroredVoucher(
            externalId: trim((string) ($row['invoice_id'] ?? '')),
            direction: DocumentDirection::Outgoing,
            kind: $isCredit ? DocumentKind::CreditNote : DocumentKind::Invoice,
            rawType: $isCredit ? 'creditinvoice' : 'invoice',
            rawStatus: $statusId > 0 ? (string) $statusId : null,
            state: self::STATES[$statusId] ?? 'open',
            number: trim((string) ($row['invoice_number'] ?? '')) ?: null,
            date: VoucherMirror::date($row['invoice_date_created'] ?? null),
            dueDate: VoucherMirror::date($row['invoice_date_due'] ?? null),
            // InvoicePlane führt kein Zahldatum am Beleg; „bezahlt" ist ein
            // Status. Ein erfundenes Datum wäre schlechter als keines.
            paidDate: null,
            totalAmount: VoucherMirror::decimal($total),
            netAmount: VoucherMirror::decimal($row['invoice_item_subtotal'] ?? null),
            openAmount: VoucherMirror::decimal($row['invoice_balance'] ?? null),
            currency: trim((string) ($row['invoice_currency'] ?? 'EUR')) ?: 'EUR',
            isCancellation: false,
            cancelsExternalId: $parent,
            contactExternalId: trim((string) ($row['client_id'] ?? '')) ?: null,
            customerNumber: trim((string) ($row['client_number'] ?? '')) ?: null,
            sourceChangedAt: VoucherMirror::timestamp(
                $row['invoice_date_modified'] ?? ($row['invoice_date_created'] ?? null),
            ),
            payload: $row,
        );
    }
}
