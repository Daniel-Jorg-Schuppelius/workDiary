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

    /** @var list<array<string, mixed>>|null Einmal je Instanz materialisiert (A13). */
    private ?array $aggregate = null;

    public function __construct(private readonly DocumentFeedFilters $filters, ?DocumentFeedSourceRegistry $sources = null) {
        $this->sources = $sources ?? app(DocumentFeedSourceRegistry::class);
    }

    /**
     * Eine Aggregation für Kennzahlen, Tab-Zähler UND die Gesamtzahl der
     * Seite (Vollscan 2026-08-23, A13). Vorher materialisierte jede der drei
     * Fragen die UNION erneut — zusammen mit der Seitenabfrage viermal je
     * Aufruf. Gruppiert wird über die Achsen, die alle drei brauchen:
     * Währung × Richtung × Vorgangsart.
     *
     * @return list<array<string, mixed>>
     */
    private function aggregate(): array {
        if ($this->aggregate !== null) {
            return $this->aggregate;
        }

        $today = Carbon::today()->toDateString();
        $expense = "source_type = 'expense'";

        $rows = $this->filtered(ignoreKindAndDirection: true)
            ->selectRaw('currency, direction, kind')
            ->selectRaw('COUNT(*) AS row_count')
            ->selectRaw('SUM(sign * amount_gross) AS signed_total')
            ->selectRaw("SUM(CASE WHEN NOT ($expense) THEN sign * amount_gross ELSE 0 END) AS signed_without_expense")
            ->selectRaw("SUM(CASE WHEN $expense THEN sign * amount_gross ELSE 0 END) AS internal_total")
            ->selectRaw("SUM(CASE WHEN $expense AND state = 'open' THEN amount_gross ELSE 0 END) AS internal_pending")
            ->selectRaw("SUM(CASE WHEN state = 'open' THEN open_amount ELSE 0 END) AS open_total")
            // Grundmenge zur Überfälligkeit: „7 Belege" beantwortet erst mit
            // dem Nenner die Frage, wovon sieben.
            ->selectRaw("SUM(CASE WHEN state = 'open' THEN 1 ELSE 0 END) AS open_count")
            ->selectRaw("SUM(CASE WHEN state = 'open' AND due_on IS NOT NULL AND due_on < ? THEN open_amount ELSE 0 END) AS overdue_total", [$today])
            ->selectRaw("SUM(CASE WHEN state = 'open' AND due_on IS NOT NULL AND due_on < ? THEN 1 ELSE 0 END) AS overdue_count", [$today])
            ->groupBy('currency', 'direction', 'kind')
            ->orderBy('currency')
            ->get();

        return $this->aggregate = array_values($rows->map(
            static fn (object $row): array => (array) $row,
        )->all());
    }

    /**
     * Gilt der Art-/Richtungsfilter für diese Gruppe? Die Achsen liegen als
     * Spalten in der Aggregation, der Filter wird deshalb hier statt in SQL
     * angelegt — sonst bräuchte er eine zweite Materialisierung.
     */
    /** @param array<string, mixed> $row */
    private function matchesKindAndDirection(array $row): bool {
        $kinds = $this->filters->kindValues();
        $directions = $this->filters->directionValues();

        return ($kinds === [] || in_array((string) $row['kind'], $kinds, true))
            && ($directions === [] || in_array((string) $row['direction'], $directions, true));
    }

    /** Zeilenzahl der gefilterten Menge — Gesamtwert des Paginators. */
    private function filteredCount(): int {
        $count = 0;
        foreach ($this->aggregate() as $row) {
            if ($this->matchesKindAndDirection($row)) {
                $count += (int) $row['row_count'];
            }
        }

        return $count;
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
            // Gesamtzahl aus der Aggregation: die Zähl-Abfrage des Paginators
            // wäre die vierte Materialisierung derselben UNION.
            ->paginate($perPage, ['*'], 'page', null, $this->filteredCount())
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
        $byCurrency = [];

        foreach ($this->aggregate() as $row) {
            if (! $this->matchesKindAndDirection($row)) {
                continue;
            }

            $currency = (string) $row['currency'];
            $bucket = $byCurrency[$currency] ?? [
                'currency' => $currency, 'revenue' => 0.0, 'expense' => 0.0, 'internal' => 0.0,
                'internalPending' => 0.0, 'balance' => 0.0, 'open' => 0.0, 'openCount' => 0,
                'overdue' => 0.0, 'overdueCount' => 0, 'neutralCount' => 0,
            ];

            $direction = (string) $row['direction'];
            if ($direction === 'outgoing') {
                $bucket['revenue'] += (float) $row['signed_total'];
            } elseif ($direction === 'incoming') {
                // Auslagen bleiben aus dem Aufwand heraus (Dublettenregel 3).
                $bucket['expense'] += (float) $row['signed_without_expense'];
            } elseif ($direction === 'neutral') {
                $bucket['neutralCount'] += (int) $row['row_count'];
            }

            $bucket['internal'] += (float) $row['internal_total'];
            $bucket['internalPending'] += (float) $row['internal_pending'];
            $bucket['open'] += (float) $row['open_total'];
            $bucket['openCount'] += (int) $row['open_count'];
            $bucket['overdue'] += (float) $row['overdue_total'];
            $bucket['overdueCount'] += (int) $row['overdue_count'];

            $byCurrency[$currency] = $bucket;
        }

        ksort($byCurrency);

        // Cent-Beträge, in PHP über die Gruppen addiert: auf zwei Stellen
        // festzurren, damit Gleitkomma-Reste nicht in der Kachel landen.
        return array_values(array_map(static function (array $bucket): array {
            foreach (['revenue', 'expense', 'internal', 'internalPending', 'open', 'overdue'] as $key) {
                $bucket[$key] = round((float) $bucket[$key], 2);
            }
            $bucket['balance'] = round($bucket['revenue'] - $bucket['expense'], 2);

            return $bucket;
        }, $byCurrency));
    }

    /**
     * Zeilenzahl je Vorgangsart und Richtung für die Tab-Zähler — aus derselben
     * Aggregation wie die Kennzahlen.
     *
     * @return array<string, int> Schlüssel `<direction>:<kind>` und `overdue`
     */
    public function tabCounts(): array {
        $counts = ['overdue' => 0];

        foreach ($this->aggregate() as $row) {
            $key = (string) $row['direction'] . ':' . (string) $row['kind'];
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row['row_count'];
            $counts['overdue'] += (int) $row['overdue_count'];
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
