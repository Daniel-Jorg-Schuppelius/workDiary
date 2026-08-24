<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed\Sources;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Enums\Expense\ExpenseStatus;
use App\Models\Expense;
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, DocumentFeedSourceRegistry, FeedProjection};
use Illuminate\Database\Query\{Builder, JoinClause};
use Illuminate\Support\Facades\DB;

/**
 * Auslagen. Sichtbarkeit folgt der ExpensePolicy: eigene immer, alle nur
 * mit Adminrecht — und dann steuert derselbe Umfang auch das
 * Kennzahlenband.
 *
 * Verknüpfte Auslagen (MVP-551) sind nicht mehr geldwirksam: dort führt
 * der zugeordnete Buchhaltungsbeleg. Welche Verknüpfungen zählen, sagen die
 * Plugin-Quellen ({@see DocumentFeedSourceRegistry::expenseLinkCriteria}).
 */
class ExpenseSource implements DocumentFeedSource {
    public function __construct(private readonly DocumentFeedSourceRegistry $registry) {}

    public function key(): string {
        return 'expense';
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('expense') || ! $f->wantsOrigin(DocumentOrigin::Local) || ! $f->wantsFixed(DocumentDirection::Incoming, DocumentKind::Expense)) {
            return null;
        }

        $state = FeedProjection::caseMap('expenses.status', [
            ExpenseStatus::Draft->value => 'draft',
            ExpenseStatus::Rejected->value => 'cancelled',
            ExpenseStatus::Cancelled->value => 'cancelled',
            ExpenseStatus::Reimbursed->value => 'paid',
            ExpenseStatus::Invoiced->value => 'paid',
        ], 'open');

        $sign = "CASE
            WHEN expenses.status IN ('" . ExpenseStatus::Rejected->value . "', '" . ExpenseStatus::Cancelled->value . "', '" . ExpenseStatus::Draft->value . "') THEN 0
            WHEN feed_link.id IS NOT NULL THEN 0
            ELSE 1 END";

        $criteria = $this->registry->expenseLinkCriteria();

        $query = DB::table('expenses')
            // Bestätigte Zuordnung zum Buchhaltungsbeleg (MVP-551) als JOIN,
            // nicht als SQL-Literal: der Klassenname enthält Backslashes, die
            // MariaDB in Stringliteralen als Escapes liest.
            ->leftJoin('external_references as feed_link', function (JoinClause $join) use ($criteria): void {
                $join->on('feed_link.referenceable_id', '=', 'expenses.id')
                    ->where('feed_link.referenceable_type', Expense::class);

                // Ohne registrierte Verknüpfungs-Quelle gibt es keine
                // zählbaren Links — der Join bleibt leer statt zu raten.
                if ($criteria === []) {
                    $join->whereRaw('1 = 0');

                    return;
                }

                $join->where(function (Builder $q) use ($criteria): void {
                    foreach ($criteria as $criterion) {
                        $q->orWhere(function (Builder $qq) use ($criterion): void {
                            $qq->where('feed_link.plugin_id', $criterion['plugin_id'])
                                ->where('feed_link.external_type', $criterion['external_type']);
                        });
                    }
                });
            })
            ->selectRaw(FeedProjection::columns([
                "'expense' AS source_type",
                'expenses.id AS source_id',
                'expenses.id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                "'" . DocumentDirection::Incoming->value . "' AS direction",
                "'" . DocumentKind::Expense->value . "' AS kind",
                "$sign AS sign",
                "COALESCE(expenses.reimbursement_reference, '') AS number",
                'expenses.date AS doc_date',
                'NULL AS due_on',
                "$state AS state",
                '0 AS is_archived',
                'NULL AS contact_type',
                'NULL AS contact_id',
                "COALESCE(expenses.vendor, '') AS contact_name",
                '0 AS dunning_level',
                'COALESCE(expenses.amount_gross, 0) AS amount_gross',
                '0 AS open_amount',
                'expenses.currency AS currency',
            ]))
            ->where('expenses.organization_id', $f->organizationId)
            ->whereBetween('expenses.date', [$f->from->toDateString(), $f->to->toDateString()]);

        if (! $f->allExpenses) {
            $query->where('expenses.user_id', $f->userId);
        }

        // Arbeitsliste „noch nicht verbucht": macht aus der Dublettengefahr
        // eine abarbeitbare Aufgabe statt einer stillen Unschärfe.
        if ($f->onlyUnlinkedExpenses) {
            $query->whereNull('feed_link.id');
        }

        return $query;
    }
}
