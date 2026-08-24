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
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, FeedProjection};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Gespiegelte Belege aus `accounting_vouchers` (Feature 122, MVP-611).
 *
 * Das sind die Belege, die DIREKT in der Buchhaltung entstanden sind —
 * Kassenbon, per Mail eingegangene Lieferantenrechnung. Ohne sie hat der
 * Belegfluss ein Loch, das niemand sieht. sevDesk kennt nur
 * Einnahme/Ausgabe (`creditDebit`), keine Belegart-Taxonomie: daraus wird
 * die Richtung abgeleitet, die Art bleibt „sonstiges“. Die Tabelle ist
 * Kern-Schema (Kern-Migration), deshalb bleibt die Quelle im Kern.
 */
class AccountingVoucherSource implements DocumentFeedSource {
    public function key(): string {
        return 'accounting_voucher';
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('voucher') || ! $f->wantsOrigin(DocumentOrigin::SevDesk)) {
            return null;
        }

        // C = Einnahme (Ausgangsrichtung), D = Ausgabe (Eingangsrichtung).
        $direction = FeedProjection::caseMap('accounting_vouchers.voucher_type', [
            'C' => DocumentDirection::Outgoing->value,
            'D' => DocumentDirection::Incoming->value,
        ], DocumentDirection::Neutral->value);
        $sign = FeedProjection::caseMap('accounting_vouchers.voucher_type', [
            'C' => '1',
            'D' => '-1',
        ], '0', quoted: false);
        // sevDesk-Statuscodes sind Zahlen; PHP macht daraus int-Schlüssel,
        // die caseMap() nicht annimmt. Deshalb explizit als String-Paare.
        /** @var array<string, string> $statusMap */
        $statusMap = array_combine(
            ['50', '100', '750', '1000'],
            ['draft', 'open', 'open', 'paid'],
        );
        $state = FeedProjection::caseMap('accounting_vouchers.voucher_status', $statusMap, 'open');

        return DB::table('accounting_vouchers')
            ->selectRaw(FeedProjection::columns([
                "'voucher' AS source_type",
                'accounting_vouchers.id AS source_id',
                'accounting_vouchers.id AS link_id',
                "'" . DocumentOrigin::SevDesk->value . "' AS origin",
                "$direction AS direction",
                "'" . DocumentKind::Other->value . "' AS kind",
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
            ->whereNotNull('accounting_vouchers.voucher_date')
            ->whereBetween('accounting_vouchers.voucher_date', [$f->from->toDateString(), $f->to->toDateString()]);
    }
}
