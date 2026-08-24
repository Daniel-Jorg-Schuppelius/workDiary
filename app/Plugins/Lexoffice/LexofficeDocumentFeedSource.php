<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeDocumentFeedSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, FeedProjection, MarksLinkedExpenses, SuppressesCoreInvoices};
use App\Support\Billing\VoucherTypes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Gespiegelte Belege des führenden Buchhaltungssystems im Belegfluss
 * (Feature 105). Registriert sich über den {@see LexofficeServiceProvider}
 * an der Feed-Registry — der Kern kennt `lexoffice_vouchers` nicht.
 *
 * Sichtbarkeit bleibt filtergesteuert (Berechtigung `voucher`, Herkunft):
 * die Registrierung selbst ist wie bisher unabhängig vom Org-Schalter des
 * Plugins, gespiegelte Zeilen existieren nur nach einem Sync.
 */
class LexofficeDocumentFeedSource implements DocumentFeedSource, MarksLinkedExpenses, SuppressesCoreInvoices {
    public function key(): string {
        return LexofficePlugin::ID;
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('voucher') || ! $f->wantsOrigin(DocumentOrigin::Lexoffice)) {
            return null;
        }

        $directionCases = [];
        $kindCases = [];
        $signCases = [];
        // array_merge, nicht `+`: bei Listen würde der Plus-Operator die
        // gleichindizierten Elemente der Folgearrays verwerfen.
        $allTypes = array_merge(
            VoucherTypes::ofDirection(DocumentDirection::Outgoing),
            VoucherTypes::ofDirection(DocumentDirection::Incoming),
            VoucherTypes::ofDirection(DocumentDirection::Neutral),
        );

        foreach ($allTypes as $type) {
            $class = VoucherTypes::classify($type);
            $directionCases[$type] = $class->direction->value;
            $kindCases[$type] = $class->kind->value;
            $signCases[$type] = (string) $class->sign();
        }

        $direction = FeedProjection::caseMap('lexoffice_vouchers.voucher_type', $directionCases, DocumentDirection::Neutral->value);
        $kind = FeedProjection::caseMap('lexoffice_vouchers.voucher_type', $kindCases, DocumentKind::Other->value);
        $signMap = FeedProjection::caseMap('lexoffice_vouchers.voucher_type', $signCases, '0', quoted: false);

        $ignored = "'" . implode("', '", VoucherTypes::IGNORED_STATUSES) . "'";
        $sign = "CASE WHEN lexoffice_vouchers.voucher_status IN ($ignored) THEN 0 ELSE $signMap END";

        $state = FeedProjection::caseMap('lexoffice_vouchers.voucher_status', [
            'draft' => 'draft',
            'voided' => 'cancelled',
            'rejected' => 'cancelled',
            'paid' => 'paid',
            'paidoff' => 'paid',
            'checked' => 'paid',
            'transferred' => 'paid',
            'accepted' => 'paid',
        ], 'open');

        return DB::table('lexoffice_vouchers')
            ->selectRaw(FeedProjection::columns([
                "'voucher' AS source_type",
                'lexoffice_vouchers.id AS source_id',
                'lexoffice_vouchers.id AS link_id',
                "'" . DocumentOrigin::Lexoffice->value . "' AS origin",
                "$direction AS direction",
                "$kind AS kind",
                "$sign AS sign",
                "COALESCE(lexoffice_vouchers.voucher_number, '') AS number",
                'lexoffice_vouchers.voucher_date AS doc_date',
                'lexoffice_vouchers.due_date AS due_on',
                "$state AS state",
                'CASE WHEN lexoffice_vouchers.archived THEN 1 ELSE 0 END AS is_archived',
                "CASE WHEN lexoffice_vouchers.customer_id IS NOT NULL THEN 'customer'
                    WHEN lexoffice_vouchers.supplier_id IS NOT NULL THEN 'supplier' ELSE NULL END AS contact_type",
                'COALESCE(lexoffice_vouchers.customer_id, lexoffice_vouchers.supplier_id) AS contact_id',
                'COALESCE(
                    (SELECT customers.name FROM customers WHERE customers.id = lexoffice_vouchers.customer_id),
                    (SELECT suppliers.name FROM suppliers WHERE suppliers.id = lexoffice_vouchers.supplier_id)
                ) AS contact_name',
                '0 AS dunning_level',
                'COALESCE(lexoffice_vouchers.total_amount, 0) AS amount_gross',
                "CASE WHEN $state = 'open'
                    THEN COALESCE(lexoffice_vouchers.open_amount, lexoffice_vouchers.total_amount, 0) ELSE 0 END AS open_amount",
                'lexoffice_vouchers.currency AS currency',
            ]))
            ->where('lexoffice_vouchers.organization_id', $f->organizationId)
            ->whereNotNull('lexoffice_vouchers.voucher_date')
            ->whereBetween('lexoffice_vouchers.voucher_date', [$f->from->toDateString(), $f->to->toDateString()]);
    }

    /**
     * Dublettenregel 1 (extern führt): eine lokale Rechnung, deren Nummer als
     * gespiegelter Beleg existiert, erscheint nur als Beleg.
     */
    public function suppressCoreInvoices(Builder $invoices): void {
        $invoices->whereNotExists(function (Builder $sub): void {
            $sub->select(DB::raw(1))
                ->from('lexoffice_vouchers')
                ->whereColumn('lexoffice_vouchers.organization_id', 'invoices.organization_id')
                ->where(function (Builder $q): void {
                    $q->whereColumn('lexoffice_vouchers.voucher_number', 'invoices.number')
                        ->orWhereColumn('lexoffice_vouchers.voucher_number', 'invoices.external_number');
                });
        });
    }

    /** Bestätigte Auslagen-Zuordnung (MVP-551, {@see \App\Services\Billing\DocumentLinks}). */
    public function expenseLinkCriteria(): array {
        return [
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_VOUCHER,
        ];
    }
}
