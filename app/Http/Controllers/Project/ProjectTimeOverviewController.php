<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeOverviewController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Classification\Tag;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\{Project, Task};
use App\Models\Time\TimeEntry;
use App\Support\{LookupCache, SortableQuery, Sqid};
use App\Support\Query\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Zeitenübersicht über alle Projekte (MVP-1073): Zeiteinträge im
 * Header-Zeitraum, gruppiert nach Projekt, Tag oder Person. Fremde Zeiten
 * zeigt die Liste nur, wem TimeEntryPolicy::view sie erlaubt.
 */
class ProjectTimeOverviewController extends Controller {
    use ResolvesGlobalDateRange;

    /** Gruppierung → Spalte, die die Gruppe bildet. */
    private const GROUP_COLUMNS = ['project' => 'project_id', 'day' => 'date', 'user' => 'user_id'];

    private const SORTS = ['date', 'project', 'user', 'minutes', 'task', 'description'];

    public function __invoke(Request $request): View {
        Gate::authorize('viewAny', Project::class);

        /** @var User $viewer */
        $viewer = $request->user();
        $seesAll = $viewer->canViewAllTimeEntries();

        $filters = $this->filters($request, $seesAll);
        $group = $this->scalar($request, 'group');
        // Wer nur eigene Zeiten sieht, hat nichts nach Person zu gruppieren.
        if (! isset(self::GROUP_COLUMNS[$group]) || ($group === 'user' && ! $seesAll)) {
            $group = 'project';
        }
        [$sort, $dir] = SortableQuery::resolve($request, self::SORTS, 'date');

        $query = $this->baseQuery($filters, $viewer);

        $entries = $this->ordered(clone $query, $group, $sort, $dir)
            ->with([
                'project:id,name,slug,color,customer_id,foreign_customer_id',
                // slug gehört in die Projekt-URL ("<kunde>/<projekt>"); ohne ihn
                // zeigen alle Links auf "intern/…" und laufen ins Leere.
                'project.customer:id,name,slug',
                'user:id,name',
                'task:id,title',
                'tags:id,name,color',
                'timesheet:id,status',
            ])
            ->paginate(50)
            ->withQueryString();

        // Die Seite zeigt nur einen Ausschnitt: Anzahl und Summe im
        // Gruppenkopf gelten trotzdem für den ganzen Zeitraum.
        $groupTotals = $this->groupTotals(clone $query, $group);
        $groups = $entries->getCollection()
            ->groupBy(fn(TimeEntry $entry): string => $this->groupKey($entry, $group))
            ->map(fn(Collection $rows, int|string $key): array => [
                'entries' => $rows,
                'count' => (int) ($groupTotals->get((string) $key)->entry_count ?? $rows->count()),
                'minutes' => (int) ($groupTotals->get((string) $key)->minutes_sum ?? $rows->sum('minutes')),
            ]);

        return view('projects.times', [
            'entries' => $entries,
            'groups' => $groups,
            'group' => $group,
            'sort' => $sort,
            'dir' => $dir,
            'filters' => $filters,
            'hasActiveFilters' => array_filter($filters, fn(string $value): bool => $value !== '') !== [],
            'seesAll' => $seesAll,
            'totals' => $this->totals(clone $query),
            'rangeLabel' => $this->globalDateRange()['label'],
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'customer_id', 'foreign_customer_id']),
            'users' => $seesAll ? LookupCache::userDropdown() : collect(),
            'tags' => LookupCache::tagOptions(),
        ]);
    }

    /** Query-Wert als Text; Array-Eingaben (`q[]=`) zählen als leer. */
    private function scalar(Request $request, string $key): string {
        $value = $request->query($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return array{q: string, customer: string, project: string, user: string, tag: string, billable: string}
     */
    private function filters(Request $request, bool $seesAll): array {
        $billable = $this->scalar($request, 'billable');

        return [
            'q' => $this->scalar($request, 'q'),
            'customer' => $this->scalar($request, 'customer'),
            'project' => $this->scalar($request, 'project'),
            'user' => $seesAll ? $this->scalar($request, 'user') : '',
            'tag' => $this->scalar($request, 'tag'),
            'billable' => in_array($billable, ['yes', 'no'], true) ? $billable : '',
        ];
    }

    /**
     * Zeiteinträge mit Projekt im Header-Zeitraum — ohne Sortierung, damit
     * Liste, Kennzahlen und Gruppensummen dieselbe Menge zählen.
     *
     * @param  array{q: string, customer: string, project: string, user: string, tag: string, billable: string}  $filters
     * @return Builder<TimeEntry>
     */
    private function baseQuery(array $filters, User $viewer): Builder {
        [$from, $to] = $this->globalDateRangeBounds();
        $customerId = Sqid::decode(Customer::class, $filters['customer']);
        $projectId = Sqid::decode(Project::class, $filters['project']);
        $userId = Sqid::decode(User::class, $filters['user']);
        $tagId = Sqid::decode(Tag::class, $filters['tag']);
        $search = $filters['q'];

        return TimeEntry::query()
            ->whereNotNull('time_entries.project_id')
            ->whereBetween('time_entries.date', DateRange::days($from, $to))
            ->visibleTo($viewer)
            ->when($userId !== null, fn(Builder $q) => $q->where('time_entries.user_id', $userId))
            ->when($projectId !== null, fn(Builder $q) => $q->where('time_entries.project_id', $projectId))
            ->when($customerId !== null, fn(Builder $q) => $q->whereHas('project', fn(Builder $p) => $p->where('customer_id', $customerId)))
            ->when($tagId !== null, fn(Builder $q) => $q->whereHas('tags', fn(Builder $t) => $t->whereKey($tagId)))
            ->when($filters['billable'] !== '', fn(Builder $q) => $q->where('time_entries.billable', $filters['billable'] === 'yes'))
            ->when($search !== '', fn(Builder $q) => $q->where(function (Builder $s) use ($search): void {
                $s->whereLikeEscaped('time_entries.description', $search)
                    ->orWhereHas('project', fn(Builder $p) => $p->whereLikeEscaped('name', $search))
                    ->orWhereHas('task', fn(Builder $t) => $t->whereLikeEscaped('title', $search));
            }));
    }

    /**
     * Erst die Gruppe, darin die gewählte Spalte; Relations-Spalten über
     * korrelierte Subqueries.
     *
     * @param  Builder<TimeEntry>  $query
     * @param  'asc'|'desc'  $dir
     * @return Builder<TimeEntry>
     */
    private function ordered(Builder $query, string $group, string $sort, string $dir): Builder {
        $projectName = Project::query()->select('name')->whereColumn('projects.id', 'time_entries.project_id');
        $userName = User::inCurrentOrganization()->select('name')->whereColumn('users.id', 'time_entries.user_id');

        match ($group) {
            'day' => $query->orderBy('time_entries.date', $sort === 'date' ? $dir : 'desc'),
            'user' => $query->orderBy($userName)->orderBy('time_entries.user_id'),
            default => $query->orderBy($projectName)->orderBy('time_entries.project_id'),
        };
        match ($sort) {
            'project' => $query->orderBy($projectName, $dir),
            'user' => $query->orderBy($userName, $dir),
            'task' => $query->orderBy(Task::query()->select('title')->whereColumn('tasks.id', 'time_entries.task_id'), $dir),
            'date' => $query->orderBy('time_entries.date', $dir)->orderBy('time_entries.started_at', $dir),
            default => $query->orderBy('time_entries.' . $sort, $dir),
        };

        return $query->orderByDesc('time_entries.id');
    }

    private function groupKey(TimeEntry $entry, string $group): string {
        return match ($group) {
            'day' => $entry->date?->toDateString() ?? '',
            'user' => (string) $entry->user_id,
            default => (string) $entry->project_id,
        };
    }

    /**
     * @param  Builder<TimeEntry>  $query
     * @return Collection<string, object{entry_count: int|string, minutes_sum: int|string}>
     */
    private function groupTotals(Builder $query, string $group): Collection {
        $column = 'time_entries.' . self::GROUP_COLUMNS[$group];

        /** @var Collection<string, object{entry_count: int|string, minutes_sum: int|string}> $totals */
        $totals = $query->toBase()
            ->select($column . ' as group_key')
            ->selectRaw('COUNT(*) as entry_count, COALESCE(SUM(time_entries.minutes), 0) as minutes_sum')
            ->groupBy($column)
            ->get()
            // SQLite liefert das Datum mit Zeitanteil, MariaDB ohne.
            ->keyBy(fn(object $row): string => $group === 'day' ? DateRange::day((string) $row->group_key) : (string) $row->group_key);

        return $totals;
    }

    /**
     * @param  Builder<TimeEntry>  $query
     * @return array{count: int, minutes: int, billable: int, projects: int}
     */
    private function totals(Builder $query): array {
        $row = $query->toBase()
            ->selectRaw('COUNT(*) as entry_count, COALESCE(SUM(time_entries.minutes), 0) as minutes_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN time_entries.billable = 1 THEN time_entries.minutes ELSE 0 END), 0) as billable_sum')
            ->selectRaw('COUNT(DISTINCT time_entries.project_id) as project_count')
            ->first();

        return [
            'count' => (int) ($row->entry_count ?? 0),
            'minutes' => (int) ($row->minutes_sum ?? 0),
            'billable' => (int) ($row->billable_sum ?? 0),
            'projects' => (int) ($row->project_count ?? 0),
        ];
    }
}
