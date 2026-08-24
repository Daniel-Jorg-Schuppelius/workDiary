<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed\Sources;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Models\Invoice;
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, DocumentFeedSourceRegistry, FeedProjection};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lokale Rechnungen. An die Buchhaltung übergebene Rechnungen übernehmen
 * deren Belegnummer — sie erscheinen dann nur einmal, und zwar als externer
 * Beleg (Dublettenregel 1: extern führt). Welche Spiegel-Quellen verdrängen,
 * sagt die Registry ({@see DocumentFeedSourceRegistry::suppressCoreInvoices}).
 */
class InvoiceSource implements DocumentFeedSource {
    public function __construct(private readonly DocumentFeedSourceRegistry $registry) {}

    public function key(): string {
        return 'invoice';
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('invoice') || ! $f->wantsOrigin(DocumentOrigin::Local) || ! $f->wantsFixed(DocumentDirection::Outgoing)) {
            return null;
        }

        $kind = FeedProjection::caseMap('invoices.type', [
            Invoice::TYPE_CREDIT_NOTE => DocumentKind::CreditNote->value,
            Invoice::TYPE_CANCELLATION => DocumentKind::Cancellation->value,
            Invoice::TYPE_DOWN_PAYMENT => DocumentKind::DownPayment->value,
            Invoice::TYPE_PARTIAL => DocumentKind::DownPayment->value,
        ], DocumentKind::Invoice->value);

        $state = FeedProjection::caseMap('invoices.status', [
            Invoice::STATUS_DRAFT => 'draft',
            Invoice::STATUS_PAID => 'paid',
            Invoice::STATUS_CANCELLED => 'cancelled',
        ], 'open');

        // Retainer-Pauschalen sind bewusst nicht erlöswirksam (Feature 098):
        // die Buchhaltung finalisiert sie, die lokale Zeile ist nur Nachweis.
        $sign = "CASE
            WHEN invoices.status IN ('" . Invoice::STATUS_DRAFT . "', '" . Invoice::STATUS_CANCELLED . "') THEN 0
            WHEN invoices.type = '" . Invoice::TYPE_RETAINER . "' THEN 0
            WHEN invoices.type IN ('" . Invoice::TYPE_CREDIT_NOTE . "', '" . Invoice::TYPE_CANCELLATION . "') THEN -1
            ELSE 1 END";

        // Über das Modell statt DB::table(): `invoices` ist
        // festschreibungspflichtig, Roh-Tabellenzugriffe sind dort gesperrt
        // (GobdLockGuardRuleTest). toBase() liefert den Query-Builder
        // inklusive Organisations-Scope.
        $query = Invoice::query()->toBase()
            ->selectRaw(FeedProjection::columns([
                "'invoice' AS source_type",
                'invoices.id AS source_id',
                'invoices.id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                "'" . DocumentDirection::Outgoing->value . "' AS direction",
                "$kind AS kind",
                "$sign AS sign",
                'invoices.number AS number',
                'COALESCE(invoices.issued_on, DATE(invoices.created_at)) AS doc_date',
                'invoices.due_on AS due_on',
                "$state AS state",
                '0 AS is_archived',
                "'customer' AS contact_type",
                'invoices.customer_id AS contact_id',
                '(SELECT customers.name FROM customers WHERE customers.id = invoices.customer_id) AS contact_name',
                'COALESCE(invoices.dunning_level, 0) AS dunning_level',
                'COALESCE(invoices.total, 0) AS amount_gross',
                "CASE WHEN $state = 'open' THEN COALESCE(invoices.total, 0) ELSE 0 END AS open_amount",
                'invoices.currency AS currency',
            ]))
            ->where('invoices.organization_id', $f->organizationId)
            ->whereBetween(DB::raw('COALESCE(invoices.issued_on, DATE(invoices.created_at))'), [$f->from->toDateString(), $f->to->toDateString()]);

        $this->registry->suppressCoreInvoices($query);

        return $query;
    }
}
