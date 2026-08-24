<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoiceSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed\Sources;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Models\IncomingEInvoice;
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, FeedProjection};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Eingangsrechnungen aus dem Prüfbereich. Übertragene Belege werden
 * ausgelassen — dort führt der Buchhaltungsbeleg (Dublettenregel 2).
 */
class IncomingEInvoiceSource implements DocumentFeedSource {
    public function key(): string {
        return 'incoming_einvoice';
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('incoming_einvoice') || ! $f->wantsOrigin(DocumentOrigin::Local) || ! $f->wantsFixed(DocumentDirection::Incoming, DocumentKind::Invoice)) {
            return null;
        }

        $state = FeedProjection::caseMap('incoming_einvoices.status', [
            IncomingEInvoice::STATUS_REJECTED => 'cancelled',
            IncomingEInvoice::STATUS_PAYMENT_RELEASED => 'paid',
        ], 'open');

        $sign = "CASE WHEN incoming_einvoices.status = '" . IncomingEInvoice::STATUS_REJECTED . "' THEN 0 ELSE 1 END";

        return DB::table('incoming_einvoices')
            ->selectRaw(FeedProjection::columns([
                "'incoming_einvoice' AS source_type",
                'incoming_einvoices.id AS source_id',
                'incoming_einvoices.document_id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                "'" . DocumentDirection::Incoming->value . "' AS direction",
                "'" . DocumentKind::Invoice->value . "' AS kind",
                "$sign AS sign",
                "COALESCE(incoming_einvoices.invoice_number, '') AS number",
                'COALESCE(incoming_einvoices.issue_date, DATE(incoming_einvoices.received_at)) AS doc_date',
                'incoming_einvoices.due_date AS due_on',
                "$state AS state",
                '0 AS is_archived',
                'NULL AS contact_type',
                'NULL AS contact_id',
                'incoming_einvoices.seller_name AS contact_name',
                '0 AS dunning_level',
                'COALESCE(incoming_einvoices.amount_gross, 0) AS amount_gross',
                "CASE WHEN $state = 'open' THEN COALESCE(incoming_einvoices.amount_gross, 0) ELSE 0 END AS open_amount",
                "COALESCE(incoming_einvoices.currency, '" . FeedProjection::defaultCurrency() . "') AS currency",
            ]))
            ->where('incoming_einvoices.organization_id', $f->organizationId)
            ->whereNull('incoming_einvoices.transferred_at')
            ->whereBetween(
                DB::raw('COALESCE(incoming_einvoices.issue_date, DATE(incoming_einvoices.received_at))'),
                [$f->from->toDateString(), $f->to->toDateString()]
            );
    }
}
