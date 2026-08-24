<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentFeedQuery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Services\Billing\Feed\{DocumentFeedSourceRegistry, FeedProjection};
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ein Belegfluss über alle registrierten Quellen (Feature 105, MVP-543/654).
 *
 * Die Quellen (lokale Rechnungen, Angebote, Buchhaltungsspiegel,
 * Eingangsrechnungen, Auslagen — Plugin-Quellen wie Lexoffice/orgaMAX bringen
 * ihre eigene {@see Feed\DocumentFeedSource}-Klasse mit) werden auf eine
 * gemeinsame Zeilenform projiziert und per `UNION ALL` zusammengeführt —
 * Sortierung, Filterung und Aggregation laufen danach in SQL. Ein
 * Zusammenführen in PHP würde bei mehreren paginierten Quellen weder sortieren
 * noch summieren können.
 *
 * Die Projektion trägt ein vorberechnetes `sign` (−1/0/+1): geldwirksame
 * Vorzeichen und Nicht-Wirksamkeit (Entwurf, Storno, Angebot) stecken damit in
 * der Zeile selbst, und jede Summe ist `SUM(sign * amount_gross)`.
 */
class DocumentFeedQuery {
    /** Sortierbare Spalten der Projektion → Feed-Spalte. */
    public const SORTS = [
        'number' => 'number',
        'date' => 'doc_date',
        'kind' => 'kind',
        'contact' => 'contact_name',
        'state' => 'state',
        'due' => 'due_on',
        'amount' => 'amount_gross',
    ];

    private readonly DocumentFeedSourceRegistry $sources;

    public function __construct(private readonly DocumentFeedFilters $filters, ?DocumentFeedSourceRegistry $sources = null) {
        $this->sources = $sources ?? app(DocumentFeedSourceRegistry::class);
    }

    /** @return LengthAwarePaginator<int, object> */
    public function paginate(int $perPage, string $sort, string $dir): LengthAwarePaginator {
        $column = self::SORTS[$sort] ?? 'doc_date';
        $direction = $dir === 'asc' ? 'asc' : 'desc';

        /** @var LengthAwarePaginator<int, object> $page */
        $page = $this->filtered()
            ->orderBy($column, $direction)
            // Stabile Reihenfolge über Quellen hinweg: gleiche Datumswerte
            // sonst je Abfrage anders sortiert (UNION garantiert nichts).
            ->orderByDesc('doc_date')
            ->orderBy('source_type')
            ->orderByDesc('source_id')
            ->paginate($perPage)
            ->withQueryString();

        return $page;
    }

    /**
     * Kennzahlen je Währung über die **gesamte** gefilterte Menge.
     *
     * Keine Umrechnung: eine Zeile je vorkommender Währung. Auslagen bleiben
     * getrennt, weil eine Auslage und die später eintreffende
     * Lieferantenrechnung ohne bestätigte Verknüpfung derselbe Aufwand sein
     * können (Dublettenregel 3).
     *
     * @return list<array{currency: string, revenue: float, expense: float, internal: float,
     *     internalPending: float, balance: float, open: float, openCount: int,
     *     overdue: float, overdueCount: int, neutralCount: int}>
     */
    public function totals(): array {
        $today = Carbon::today()->toDateString();
        $expense = "source_type = 'expense'";

        $rows = $this->filtered()
            ->selectRaw('currency')
            ->selectRaw("SUM(CASE WHEN direction = 'outgoing' THEN sign * amount_gross ELSE 0 END) AS revenue")
            ->selectRaw("SUM(CASE WHEN direction = 'incoming' AND NOT ($expense) THEN sign * amount_gross ELSE 0 END) AS expense_total")
            ->selectRaw("SUM(CASE WHEN $expense THEN sign * amount_gross ELSE 0 END) AS internal_total")
            ->selectRaw("SUM(CASE WHEN $expense AND state = 'open' THEN amount_gross ELSE 0 END) AS internal_pending")
            ->selectRaw("SUM(CASE WHEN state = 'open' THEN open_amount ELSE 0 END) AS open_total")
            // Grundmenge zur Überfälligkeit: „7 Belege" beantwortet erst mit
            // dem Nenner die Frage, wovon sieben.
            ->selectRaw("SUM(CASE WHEN state = 'open' THEN 1 ELSE 0 END) AS open_count")
            ->selectRaw("SUM(CASE WHEN state = 'open' AND due_on IS NOT NULL AND due_on < ? THEN open_amount ELSE 0 END) AS overdue_total", [$today])
            ->selectRaw("SUM(CASE WHEN state = 'open' AND due_on IS NOT NULL AND due_on < ? THEN 1 ELSE 0 END) AS overdue_count", [$today])
            ->selectRaw("SUM(CASE WHEN direction = 'neutral' THEN 1 ELSE 0 END) AS neutral_count")
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        return array_values($rows->map(static fn(object $row): array => [
            'currency' => (string) $row->currency,
            'revenue' => (float) $row->revenue,
            'expense' => (float) $row->expense_total,
            'internal' => (float) $row->internal_total,
            'internalPending' => (float) $row->internal_pending,
            'balance' => (float) $row->revenue - (float) $row->expense_total,
            'open' => (float) $row->open_total,
            'openCount' => (int) $row->open_count,
            'overdue' => (float) $row->overdue_total,
            'overdueCount' => (int) $row->overdue_count,
            'neutralCount' => (int) $row->neutral_count,
        ])->all());
    }

    /**
     * Zeilenzahl je Vorgangsart und Richtung für die Tab-Zähler — ein Lauf
     * statt eines Zählers je Tab.
     *
     * @return array<string, int> Schlüssel `<direction>:<kind>` und `overdue`
     */
    public function tabCounts(): array {
        $today = Carbon::today()->toDateString();

        $rows = $this->filtered(ignoreKindAndDirection: true)
            ->selectRaw('direction, kind, COUNT(*) AS row_count')
            ->selectRaw("SUM(CASE WHEN state = 'open' AND due_on IS NOT NULL AND due_on < ? THEN 1 ELSE 0 END) AS overdue_count", [$today])
            ->groupBy('direction', 'kind')
            ->get();

        $counts = ['overdue' => 0];
        foreach ($rows as $row) {
            $counts[$row->direction . ':' . $row->kind] = (int) $row->row_count;
            $counts['overdue'] += (int) $row->overdue_count;
        }

        return $counts;
    }

    /**
     * Gefilterte Projektion. Art-, Richtungs- und Statusfilter greifen außen
     * auf den normalisierten Spalten — die Quellen selbst filtern nur, was
     * ihre Indizes bedienen können (Organisation, Zeitraum, Sichtbarkeit).
     */
    private function filtered(bool $ignoreKindAndDirection = false): Builder {
        $f = $this->filters;
        $query = DB::query()->fromSub($this->union(), 'feed');

        if (! $ignoreKindAndDirection) {
            if ($f->kindValues() !== []) {
                $query->whereIn('kind', $f->kindValues());
            }
            if ($f->directionValues() !== []) {
                $query->whereIn('direction', $f->directionValues());
            }
        }

        if ($f->origin !== null) {
            $query->where('origin', $f->origin->value);
        }

        if ($f->contactType !== null && $f->contactType !== '') {
            $query->where('contact_type', $f->contactType);
        }

        if ($f->customerId !== null) {
            $query->where('contact_type', 'customer')->where('contact_id', $f->customerId);
        }

        if ($f->state !== null && $f->state !== '') {
            $query->where('state', $f->state);
        }

        if (! $f->includeArchived) {
            $query->where('is_archived', 0);
        }

        if ($f->onlyOverdue) {
            $query->where('state', 'open')
                ->whereNotNull('due_on')
                ->where('due_on', '<', Carbon::today()->toDateString());
        }

        if ($f->search !== '') {
            // Deutsche Betragseingabe (1.167,08) → 1167.08 für den Spaltenvergleich.
            $amount = str_replace(',', '.', str_replace(['.', ' '], '', $f->search));
            $datePatterns = $this->dateLikePatterns($f->search);

            $query->where(function (Builder $q) use ($f, $amount, $datePatterns): void {
                $q->whereLikeEscaped('number', $f->search)
                    ->orWhereLikeEscaped('contact_name', $f->search);

                if (is_numeric($amount)) {
                    $q->orWhereLikeEscaped('amount_gross', $amount);
                }

                foreach ($datePatterns as $pattern) {
                    $q->orWhere('doc_date', 'like', $pattern);
                }
            });
        }

        return $query;
    }

    /**
     * Übersetzt eine deutsche/ISO/teilweise Datumseingabe in LIKE-Muster gegen
     * die als `Y-m-d` gespeicherte Datumsspalte. Unterstützt `29.06.2026`,
     * `06.2026`, `2026`, `29.06` (jahresunabhängig) sowie ISO.
     *
     * @return list<string>
     */
    private function dateLikePatterns(string $search): array {
        $s = trim($search);

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $s, $m)) {
            return [sprintf('%04d-%02d-%02d%%', (int) $m[3], (int) $m[2], (int) $m[1])];
        }
        if (preg_match('/^(\d{1,2})\.(\d{4})$/', $s, $m)) {
            return [sprintf('%04d-%02d%%', (int) $m[2], (int) $m[1])];
        }
        if (preg_match('/^(\d{4})$/', $s, $m)) {
            return [sprintf('%04d%%', (int) $m[1])];
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})$/', $s, $m)) {
            return [sprintf('%%-%02d-%02d', (int) $m[2], (int) $m[1])];
        }
        if (preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $s)) {
            return [$s . '%'];
        }

        return [];
    }

    /** Vereinigung aller registrierten Quellen, die der Filter zulässt. */
    private function union(): Builder {
        $parts = [];
        foreach ($this->sources->sources() as $source) {
            $builder = $source->builder($this->filters);
            if ($builder !== null) {
                $parts[] = $builder;
            }
        }

        if ($parts === []) {
            // Kein Sub-Select passt zum Filter: leere, aber spaltengleiche Menge.
            return $this->emptySet();
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return $union;
    }

    /** Spaltengleiche Leermenge, falls kein Sub-Select zum Filter passt. */
    private function emptySet(): Builder {
        return Invoice::query()->toBase()
            ->selectRaw(FeedProjection::columns([
                "'invoice' AS source_type",
                'invoices.id AS source_id',
                'invoices.id AS link_id',
                "'' AS origin",
                "'' AS direction",
                "'' AS kind",
                '0 AS sign',
                "'' AS number",
                'invoices.issued_on AS doc_date',
                'invoices.due_on AS due_on',
                "'' AS state",
                '0 AS is_archived',
                'NULL AS contact_type',
                'NULL AS contact_id',
                "'' AS contact_name",
                '0 AS dunning_level',
                '0 AS amount_gross',
                '0 AS open_amount',
                "'" . FeedProjection::defaultCurrency() . "' AS currency",
            ]))
            ->whereRaw('1 = 0');
    }
}
