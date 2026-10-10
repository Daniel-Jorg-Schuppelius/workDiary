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
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, FeedProjection};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Belege aus dem Rechnungseingang. Übertragene Belege werden ausgelassen —
 * dort führt der Buchhaltungsbeleg (Dublettenregel 2). Seit MVP-1107 tragen
 * sie Richtung und Art selbst (Ausgangskopien, Gutschriften); Klärfälle ohne
 * Betrag und Kopien eigener Rechnungen zählen nicht.
 */
class IncomingEInvoiceSource implements DocumentFeedSource {
    public function key(): string {
        return 'incoming_einvoice';
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('incoming_einvoice') || ! $f->wantsOrigin(DocumentOrigin::Local)
            || (! $f->wantsFixed(DocumentDirection::Incoming) && ! $f->wantsFixed(DocumentDirection::Outgoing))) {
            return null;
        }

        $state = FeedProjection::caseMap('incoming_einvoices.status', [
            IncomingEInvoiceStatus::Rejected->value => 'cancelled',
            IncomingEInvoiceStatus::PaymentReleased->value => 'paid',
        ], 'open');

        $sign = "CASE WHEN incoming_einvoices.status = '" . IncomingEInvoiceStatus::Rejected->value . "' THEN 0"
            . " WHEN incoming_einvoices.kind = '" . DocumentKind::CreditNote->value . "' THEN -1 ELSE 1 END";
        $outgoing = "incoming_einvoices.direction = '" . DocumentDirection::Outgoing->value . "'";

        return DB::table('incoming_einvoices')
            ->selectRaw(FeedProjection::columns([
                "'incoming_einvoice' AS source_type",
                'incoming_einvoices.id AS source_id',
                'incoming_einvoices.document_id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                'incoming_einvoices.direction AS direction',
                'incoming_einvoices.kind AS kind',
                "$sign AS sign",
                "COALESCE(incoming_einvoices.invoice_number, '') AS number",
                'COALESCE(incoming_einvoices.issue_date, DATE(incoming_einvoices.received_at)) AS doc_date',
                'incoming_einvoices.due_date AS due_on',
                "$state AS state",
                '0 AS is_archived',
                'NULL AS contact_type',
                'NULL AS contact_id',
                "CASE WHEN $outgoing THEN incoming_einvoices.buyer_name ELSE incoming_einvoices.seller_name END AS contact_name",
                '0 AS dunning_level',
                'COALESCE(incoming_einvoices.amount_gross, 0) AS amount_gross',
                "CASE WHEN $state = 'open' THEN COALESCE(incoming_einvoices.amount_gross, 0) ELSE 0 END AS open_amount",
                "COALESCE(incoming_einvoices.currency, '" . FeedProjection::defaultCurrency() . "') AS currency",
            ]))
            ->where('incoming_einvoices.organization_id', $f->organizationId)
            ->whereNull('incoming_einvoices.transferred_at')
            ->where(static fn (Builder $q) => $q->where('incoming_einvoices.recognition', '!=', IncomingInvoiceRecognition::None->value)
                ->orWhereNotNull('incoming_einvoices.amount_gross'))
            // Kopie einer eigenen Rechnung: dort führt die lokale Rechnung.
            ->whereNotExists(static fn (Builder $q) => $q->selectRaw('1')->from('invoices')
                ->whereColumn('invoices.organization_id', 'incoming_einvoices.organization_id')
                ->whereColumn('invoices.number', 'incoming_einvoices.invoice_number')
                ->where('incoming_einvoices.direction', DocumentDirection::Outgoing->value))
            ->whereBetween(
                DB::raw('COALESCE(incoming_einvoices.issue_date, DATE(incoming_einvoices.received_at))'),
                [$f->from->toDateString(), $f->to->toDateString()]
            );
    }
}
