<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\CustomerPortal\PortalTimeDetail;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Services\CustomerPortal\{PortalInsights, PortalTimeEntries, PortalVisibility};
use App\Support\Query\DateRange;
use App\Support\{SortableQuery, Sqid};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\DateHelper;
use Illuminate\Database\Eloquent\{Builder, Collection, Model};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\View\View;

/**
 * Projektzeiten im Kundenportal (MVP-511, Jahr und Filter MVP-1238): Umfang
 * und Detailtiefe folgen der kundenspezifischen Freigabe — `summary` zeigt
 * nur Summen, `entries` die Einzelzeilen ohne Beschreibung,
 * `entries_with_description` zusätzlich die Beschreibung ausschließlich
 * veröffentlichter Einträge. Die Grundmenge (Scope, Jahresgrenze) bestimmt
 * {@see PortalTimeEntries}; Filterwerte stammen nur aus dieser Grundmenge,
 * fremde Sqids verschwinden still. Interne Kosten, Sätze, Tags und
 * Integrationsdaten sind nie Portalinhalt.
 */
class TimeEntryController extends Controller {
    /** @var list<string> Sortierschlüssel der Einzelzeilen. */
    private const SORTS = ['date', 'minutes', 'project', 'user'];

    public function index(Request $request, PortalVisibility $visibility, PortalTimeEntries $portalTimes): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        $customer = $user->customer;
        abort_if($customer === null, 404);

        $detail = $visibility->timeDetail($customer);
        abort_if($detail === PortalTimeDetail::None, 404);

        $years = $portalTimes->years($customer);
        $year = PortalInsights::pickYear((int) $request->query('year', 0), $years);
        $yearQuery = $portalTimes->yearQuery($customer, $year);

        // Auswahlwerte nur aus der Grundmenge des Jahres; der Mitarbeiterfilter
        // existiert nur in Stufen, die die Spalte zeigen (E4).
        $projects = Project::query()
            ->whereIn('id', (clone $yearQuery)->select('time_entries.project_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
        $projectId = $this->selectedId(Project::class, $request->query('project'), $projects);

        $users = $detail->showsEntries()
            ? User::query()->whereIn('id', (clone $yearQuery)->select('time_entries.user_id'))->orderBy('name')->get(['id', 'name'])
            : new Collection();
        $userId = $detail->showsEntries() ? $this->selectedId(User::class, $request->query('user'), $users) : null;

        [$from, $to] = $this->range($request, $year);
        $search = $detail === PortalTimeDetail::EntriesWithDescription ? trim((string) $request->query('q', '')) : '';

        $filtered = (clone $yearQuery)
            ->when($projectId !== null, fn (Builder $q) => $q->where('time_entries.project_id', $projectId))
            ->when($userId !== null, fn (Builder $q) => $q->where('time_entries.user_id', $userId))
            ->when($from !== null || $to !== null, fn (Builder $q) => $q->whereBetween(
                'time_entries.date',
                DateRange::days($from ?? sprintf('%04d-01-01', $year), $to ?? sprintf('%04d-12-31', $year)),
            ))
            // Beschreibungen gibt es nur für veröffentlichte Einträge — die Suche darf nichts anderes treffen.
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->whereNotNull('time_entries.customer_visible_at')->whereLikeEscaped('time_entries.description', $search);
            });

        $kpis = [
            'year_minutes' => (int) (clone $yearQuery)->sum('time_entries.minutes'),
            'filtered_minutes' => (int) (clone $filtered)->sum('time_entries.minutes'),
            'entries' => (clone $filtered)->count(),
            'projects' => (clone $filtered)->distinct()->count('time_entries.project_id'),
        ];

        $filters = [
            'year' => $year,
            'project' => $projectId !== null ? Sqid::encode(Project::class, $projectId) : null,
            'user' => $userId !== null ? Sqid::encode(User::class, $userId) : null,
            'from' => $from,
            'to' => $to,
            'q' => $search !== '' ? $search : null,
        ];

        $shared = [
            'detail' => $detail,
            'year' => $year,
            'years' => $years === [] ? [$year] : $years,
            'projects' => $projects,
            'users' => $users,
            'filters' => $filters,
            'kpis' => $kpis,
            'oldestVisibleDate' => $portalTimes->oldestVisibleDate($customer),
        ];

        if ($detail === PortalTimeDetail::Summary) {
            // Nur freigegebene Summen: je Monat und Projekt, keine Einzelzeilen.
            // SUBSTR statt DATE_FORMAT: `date` liegt als 'Y-m-d …' vor und der
            // Ausdruck läuft auf MariaDB wie SQLite (Tests).
            $summaries = (clone $filtered)
                ->join('projects', 'projects.id', '=', 'time_entries.project_id')
                ->groupBy('projects.name', DB::raw('SUBSTR(time_entries.date, 1, 7)'))
                ->orderByDesc(DB::raw('SUBSTR(time_entries.date, 1, 7)'))
                ->orderBy('projects.name')
                ->get([
                    DB::raw('SUBSTR(time_entries.date, 1, 7) as month'),
                    DB::raw('projects.name as project_name'),
                    DB::raw('SUM(time_entries.minutes) as minutes'),
                ]);

            return view('customer.time-entries.index', $shared + [
                'summaries' => $summaries,
                'entries' => null,
                'sort' => 'date',
                'dir' => 'desc',
            ]);
        }

        [$sort, $dir] = SortableQuery::resolve($request, self::SORTS, 'date', 'desc');
        $this->applySort($filtered, $sort, $dir, (int) $customer->organization_id);

        $entries = $filtered
            ->with(['project:id,name,customer_id', 'user:id,name'])
            ->paginate(25)
            ->withQueryString();

        return view('customer.time-entries.index', $shared + [
            'summaries' => null,
            'entries' => $entries,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /**
     * Sqid aus dem Filter nur übernehmen, wenn das Modell zur Auswahl gehört (L5).
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  Collection<int, TModel>  $options
     */
    private function selectedId(string $modelClass, mixed $raw, Collection $options): ?int {
        $id = is_string($raw) ? Sqid::decode($modelClass, $raw) : null;

        return $id !== null && $options->contains(static fn (Model $model): bool => (int) $model->getKey() === $id) ? $id : null;
    }

    /**
     * Von/Bis innerhalb des Jahres; ungültige oder jahresfremde Werte entfallen.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function range(Request $request, int $year): array {
        $parse = static function (mixed $value) use ($year): ?string {
            // Strikt: nur ein kalendergültiges ISO-Datum des gewählten Jahres (kein „31.02." → 3. März).
            if (! is_string($value) || ! DateHelper::isDateTime($value, 'Y-m-d')) {
                return null;
            }
            $date = CarbonImmutable::parse($value);

            return $date->format('Y-m-d') === $value && $date->year === $year ? $value : null;
        };
        $from = $parse($request->query('from'));
        $to = $parse($request->query('to'));
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * @param  Builder<TimeEntry>  $query
     * @param  'asc'|'desc'  $dir
     */
    private function applySort(Builder $query, string $sort, string $dir, int $organizationId): void {
        match ($sort) {
            'minutes' => $query->orderBy('time_entries.minutes', $dir),
            'project' => $query->orderBy(Project::query()->select('name')->whereColumn('projects.id', 'time_entries.project_id'), $dir),
            'user' => $query->orderBy(User::query()->forOrganization($organizationId)->select('name')->whereColumn('users.id', 'time_entries.user_id'), $dir),
            default => $query->orderBy('time_entries.date', $dir),
        };
        $query->orderBy('time_entries.id', $dir);
    }
}
