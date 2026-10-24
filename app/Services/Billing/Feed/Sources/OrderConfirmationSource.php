<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed\Sources;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Enums\Sales\OrderConfirmationStatus;
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, FeedProjection};
use App\Support\Query\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Auftragsbestätigungen (Phase 145, MVP-1162) — ohne Geldwirkung, Teil des Vorgangsflusses. */
class OrderConfirmationSource implements DocumentFeedSource {
    public function key(): string {
        return 'order_confirmation';
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('order_confirmation') || ! $f->wantsOrigin(DocumentOrigin::Local) || ! $f->wantsFixed(DocumentDirection::Neutral, DocumentKind::OrderConfirmation)) {
            return null;
        }

        $state = FeedProjection::caseMap('order_confirmations.status', [
            OrderConfirmationStatus::Draft->value => 'draft',
            OrderConfirmationStatus::Cancelled->value => 'cancelled',
        ], 'open');
        $date = 'COALESCE(order_confirmations.confirmed_on, DATE(order_confirmations.created_at))';

        return DB::table('order_confirmations')
            ->selectRaw(FeedProjection::columns([
                "'order_confirmation' AS source_type",
                'order_confirmations.id AS source_id',
                'order_confirmations.id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                "'" . DocumentDirection::Neutral->value . "' AS direction",
                "'" . DocumentKind::OrderConfirmation->value . "' AS kind",
                '0 AS sign',
                'order_confirmations.number AS number',
                "$date AS doc_date",
                'NULL AS due_on',
                "$state AS state",
                '0 AS is_archived',
                "'customer' AS contact_type",
                'order_confirmations.customer_id AS contact_id',
                '(SELECT customers.name FROM customers WHERE customers.id = order_confirmations.customer_id) AS contact_name',
                '0 AS dunning_level',
                'COALESCE(order_confirmations.total, 0) AS amount_gross',
                '0 AS open_amount',
                'order_confirmations.currency AS currency',
                'order_confirmations.project_id AS project_id',
                'NULL AS foreign_amount',
                'NULL AS foreign_currency',
            ]))
            ->where('order_confirmations.organization_id', $f->organizationId)
            ->whereBetween(DB::raw($date), DateRange::days($f->from, $f->to));
    }
}
