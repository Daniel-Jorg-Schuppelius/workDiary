<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteSource.php
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

/** Angebote — ohne Geldwirkung, aber Teil des Vorgangsflusses. */
class QuoteSource implements DocumentFeedSource {
    public function key(): string {
        return 'quote';
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('quote') || ! $f->wantsOrigin(DocumentOrigin::Local) || ! $f->wantsFixed(DocumentDirection::Neutral, DocumentKind::Quote)) {
            return null;
        }

        $state = FeedProjection::caseMap('quotes.status', [
            'draft' => 'draft',
            'accepted' => 'paid',
            'partially_accepted' => 'paid',
            'rejected' => 'cancelled',
            'expired' => 'cancelled',
        ], 'open');

        return DB::table('quotes')
            ->selectRaw(FeedProjection::columns([
                "'quote' AS source_type",
                'quotes.id AS source_id',
                'quotes.id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                "'" . DocumentDirection::Neutral->value . "' AS direction",
                "'" . DocumentKind::Quote->value . "' AS kind",
                '0 AS sign',
                'quotes.number AS number',
                'DATE(quotes.created_at) AS doc_date',
                'quotes.valid_until AS due_on',
                "$state AS state",
                '0 AS is_archived',
                "'customer' AS contact_type",
                'quotes.customer_id AS contact_id',
                '(SELECT customers.name FROM customers WHERE customers.id = quotes.customer_id) AS contact_name',
                '0 AS dunning_level',
                'COALESCE(quotes.total, 0) AS amount_gross',
                '0 AS open_amount',
                "'" . FeedProjection::defaultCurrency() . "' AS currency",
            ]))
            ->where('quotes.organization_id', $f->organizationId)
            ->whereBetween(DB::raw('DATE(quotes.created_at)'), [$f->from->toDateString(), $f->to->toDateString()]);
    }
}
