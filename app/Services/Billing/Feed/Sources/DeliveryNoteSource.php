<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNoteSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed\Sources;

use App\Enums\Billing\{DeliveryNoteStatus, DocumentDirection, DocumentKind, DocumentOrigin};
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, FeedProjection};
use App\Support\Query\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Lieferscheine (Phase 145, MVP-1163) — ohne Geldwirkung, Teil des Vorgangsflusses. */
class DeliveryNoteSource implements DocumentFeedSource {
    public function key(): string {
        return 'delivery_note';
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('delivery_note') || ! $f->wantsOrigin(DocumentOrigin::Local) || ! $f->wantsFixed(DocumentDirection::Neutral, DocumentKind::DeliveryNote)) {
            return null;
        }

        $state = FeedProjection::caseMap('delivery_notes.status', [
            DeliveryNoteStatus::Draft->value => 'draft',
            DeliveryNoteStatus::Cancelled->value => 'cancelled',
        ], 'open');
        $date = 'COALESCE(delivery_notes.delivered_on, DATE(delivery_notes.created_at))';

        return DB::table('delivery_notes')
            ->selectRaw(FeedProjection::columns([
                "'delivery_note' AS source_type",
                'delivery_notes.id AS source_id',
                'delivery_notes.id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                "'" . DocumentDirection::Neutral->value . "' AS direction",
                "'" . DocumentKind::DeliveryNote->value . "' AS kind",
                '0 AS sign',
                'delivery_notes.number AS number',
                "$date AS doc_date",
                'NULL AS due_on',
                "$state AS state",
                '0 AS is_archived',
                "'customer' AS contact_type",
                'delivery_notes.customer_id AS contact_id',
                '(SELECT customers.name FROM customers WHERE customers.id = delivery_notes.customer_id) AS contact_name',
                '0 AS dunning_level',
                '0 AS amount_gross',
                '0 AS open_amount',
                "'" . FeedProjection::defaultCurrency() . "' AS currency",
                'delivery_notes.project_id AS project_id',
            ]))
            ->where('delivery_notes.organization_id', $f->organizationId)
            ->whereBetween(DB::raw($date), DateRange::days($f->from, $f->to));
    }
}
