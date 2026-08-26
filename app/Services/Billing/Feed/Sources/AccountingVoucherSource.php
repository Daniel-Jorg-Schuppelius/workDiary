<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed\Sources;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\InvoicePlane\Services\InvoicePlaneVoucherPullService;
use App\Plugins\JtlWawi\JtlWawiPlugin;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, FeedProjection};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Gespiegelte Belege aus `accounting_vouchers` (Feature 122, MVP-611;
 * anbieterneutral seit MVP-731).
 *
 * Das sind die Belege, die DIREKT in der Buchhaltung entstanden sind —
 * Kassenbon, per Mail eingegangene Lieferantenrechnung. Ohne sie hat der
 * Belegfluss ein Loch, das niemand sieht. Die Tabelle ist Kern-Schema
 * (Kern-Migration), deshalb bleibt die Quelle im Kern.
 *
 * Richtung, Belegart und Zustand liest die Projektion aus den normalisierten
 * Spalten, die jeder Puller füllt. Für Zeilen, die vor MVP-731 gespiegelt
 * wurden, greift der alte sevDesk-Pfad als Fallback (C/D + Statuszahlen) —
 * eine Backfill-Migration hätte Daten umgeschrieben, die das Fremdsystem
 * führt, und beim nächsten Lauf ohnehin überschrieben werden.
 */
class AccountingVoucherSource implements DocumentFeedSource {
    public function key(): string {
        return 'accounting_voucher';
    }

    /**
     * Plugin-ID je Herkunftssystem — die einzige Stelle, an der die Spiegelung
     * weiß, welcher Anbieter hinter einer Zeile steckt.
     *
     * @return array<string, DocumentOrigin>
     */
    private function origins(): array {
        return [
            SevDeskPlugin::ID => DocumentOrigin::SevDesk,
            EasybillPlugin::ID => DocumentOrigin::Easybill,
            InvoicePlaneVoucherPullService::PLUGIN_ID => DocumentOrigin::InvoicePlane,
            JtlWawiPlugin::ID => DocumentOrigin::JtlWawi,
        ];
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('voucher')) {
            return null;
        }

        $origins = array_filter(
            $this->origins(),
            static fn (DocumentOrigin $origin): bool => $f->wantsOrigin($origin),
        );
        if ($origins === []) {
            return null;
        }

        /** @var array<string, string> $originMap */
        $originMap = array_map(static fn (DocumentOrigin $o): string => $o->value, $origins);
        $origin = FeedProjection::caseMap('accounting_vouchers.plugin_id', $originMap, DocumentOrigin::Local->value);

        // Normalisierte Spalten zuerst; C/D bleibt Fallback für Altzeilen
        // (sevDesk: C = Einnahme/ausgehend, D = Ausgabe/eingehend).
        $legacyDirection = FeedProjection::caseMap('accounting_vouchers.voucher_type', [
            'C' => DocumentDirection::Outgoing->value,
            'D' => DocumentDirection::Incoming->value,
        ], DocumentDirection::Neutral->value);
        $direction = "COALESCE(accounting_vouchers.direction, $legacyDirection)";
        $sign = "CASE $direction WHEN '" . DocumentDirection::Outgoing->value . "' THEN 1"
            . " WHEN '" . DocumentDirection::Incoming->value . "' THEN -1 ELSE 0 END";
        $kind = "COALESCE(accounting_vouchers.document_kind, '" . DocumentKind::Other->value . "')";
        // sevDesk-Statuscodes sind Zahlen; PHP macht daraus int-Schlüssel,
        // die caseMap() nicht annimmt. Deshalb explizit als String-Paare.
        /** @var array<string, string> $statusMap */
        $statusMap = array_combine(
            ['50', '100', '750', '1000'],
            ['draft', 'open', 'open', 'paid'],
        );
        $legacyState = FeedProjection::caseMap('accounting_vouchers.voucher_status', $statusMap, 'open');
        $state = "COALESCE(accounting_vouchers.voucher_state, $legacyState)";

        return DB::table('accounting_vouchers')
            ->selectRaw(FeedProjection::columns([
                "'voucher' AS source_type",
                'accounting_vouchers.id AS source_id',
                'accounting_vouchers.id AS link_id',
                "$origin AS origin",
                "$direction AS direction",
                "$kind AS kind",
                "$sign AS sign",
                "COALESCE(accounting_vouchers.voucher_number, '') AS number",
                'accounting_vouchers.voucher_date AS doc_date',
                'accounting_vouchers.due_date AS due_on',
                "$state AS state",
                'CASE WHEN accounting_vouchers.archived THEN 1 ELSE 0 END AS is_archived',
                "CASE WHEN accounting_vouchers.customer_id IS NOT NULL THEN 'customer'
                    WHEN accounting_vouchers.supplier_id IS NOT NULL THEN 'supplier' ELSE NULL END AS contact_type",
                'COALESCE(accounting_vouchers.customer_id, accounting_vouchers.supplier_id) AS contact_id',
                'COALESCE(
                    (SELECT customers.name FROM customers WHERE customers.id = accounting_vouchers.customer_id),
                    (SELECT suppliers.name FROM suppliers WHERE suppliers.id = accounting_vouchers.supplier_id)
                ) AS contact_name',
                '0 AS dunning_level',
                'COALESCE(accounting_vouchers.total_amount, 0) AS amount_gross',
                "CASE WHEN $state = 'open'
                    THEN COALESCE(accounting_vouchers.open_amount, accounting_vouchers.total_amount, 0) ELSE 0 END AS open_amount",
                'accounting_vouchers.currency AS currency',
            ]))
            ->where('accounting_vouchers.organization_id', $f->organizationId)
            ->whereIn('accounting_vouchers.plugin_id', array_keys($origins))
            ->whereNotNull('accounting_vouchers.voucher_date')
            ->whereBetween('accounting_vouchers.voucher_date', [$f->from->toDateString(), $f->to->toDateString()]);
    }
}
