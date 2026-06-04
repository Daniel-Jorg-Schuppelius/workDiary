<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Project\ProjectStatus;
use App\Http\Requests\SaveProjectRequest;
use App\Models\{DiaryEntry, Project, RecurrenceRule, Task, Team, User};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\{LengthAwarePaginator, Paginator};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\View\View;

class ProjectController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Project::class);

        $statusFilter = $request->string('status')->toString();
        $search = $request->string('q')->toString();
        $query = Project::query();
        if (in_array($statusFilter, ProjectStatus::values(), true)) {
            $query->where('status', $statusFilter);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $projects = $query->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->select(['id', 'name', 'slug', 'color', 'status', 'description', 'starts_on', 'ends_on', 'parent_id', 'customer_id', 'foreign_customer_id'])
            ->with(['parent:id,name', 'customer:id,name,slug', 'foreignCustomer:id,name,color'])
            ->get();

        // Hierarchische, flache Zeilenliste aufbauen: Wurzel-Projekte (oder Waisen,
        // deren Parent nicht im gefilterten Set liegt) gefolgt von ihren Kindern.
        $byId = $projects->keyBy('id');
        $childrenByParent = $projects->groupBy(fn(Project $p): int => $p->parent_id ?? 0);

        $roots = $projects
            ->filter(fn(Project $p) => $p->parent_id === null || ! $byId->has($p->parent_id))
            ->values();

        // Pagination auf Ebene der Wurzel-Projekte, damit Bäume nicht zerschnitten werden.
        $perPage = 25;
        $page = Paginator::resolveCurrentPage();
        $pageRoots = $roots->forPage($page, $perPage);

        $rows = collect();
        $emit = function (Project $project, int $depth) use (&$emit, $childrenByParent, $rows): void {
            $rows->push(['project' => $project, 'depth' => min($depth, 2)]);
            foreach ($childrenByParent->get($project->id, collect()) as $child) {
                $emit($child, $depth + 1);
            }
        };
        foreach ($pageRoots as $root) {
            $emit($root, 0);
        }

        $projectsPaginator = new LengthAwarePaginator(
            $pageRoots->values(),
            $roots->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // Aggregationen nur für die tatsächlich sichtbaren Projekte berechnen.
        $projectIds = $rows->pluck('project.id')->all();

        // Einzel-Query für alle Aggregationen (statt 3 separater Queries)
        $aggr = DiaryEntry::query()
            ->select('project_id', 'status', DB::raw('COUNT(*) as cnt'), DB::raw('MAX(start_at) as last_at'), DB::raw('COUNT(DISTINCT user_id) as user_cnt'))
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id', 'status')
            ->get()
            ->groupBy('project_id');

        // View-kompatible Strukturen aus einer einzigen Query ableiten
        $stats = $aggr;
        $lastEntries = $aggr->map(fn(Collection $rows) => $rows->max('last_at'));
        $userCounts = $aggr->map(fn(Collection $rows) => $rows->max('user_cnt'));

        return view('projects.index', [
            'rows' => $rows,
            'projects' => $projectsPaginator,
            'stats' => $stats,
            'lastEntries' => $lastEntries,
            'userCounts' => $userCounts,
            'statusFilter' => $statusFilter,
            'search' => $search,
        ]);
    }

    public function show(Project $project): View {
        Gate::authorize('view', $project);

        // Aufträge (Tab 4): alle DiaryEntries, die das Projekt entweder als
        // Initialprojekt haben ODER über mind. einen TimeEntry mit diesem
        // Projekt verknüpft sind. Sortierung nach letzter Aktivität —
        // Backlog/Deadline/Window haben kein start_at.
        $entries = DiaryEntry::query()
            ->with(['user:id,name', 'tags:id,name,color'])
            ->where(function ($q) use ($project): void {
                $q->where('project_id', $project->id)
                    ->orWhereIn('id', function ($sub) use ($project): void {
                        $sub->select('diary_entry_id')
                            ->from('time_entries')
                            ->where('project_id', $project->id)
                            ->whereNotNull('diary_entry_id');
                    });
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        // Milestones mit Tasks (Tab 1 + 2)
        $milestones = $project->milestones()
            ->with(['tasks' => function ($q): void {
                $q->whereNull('parent_task_id')->orderBy('position');
            }])
            ->get();

        // Alle Toplevel-Tasks des Projekts (Tab 2)
        $topTasks = $project->tasks()
            ->with(['assignees:id,name', 'milestone:id,title', 'subTasks'])
            ->whereNull('parent_task_id')
            ->orderBy('milestone_id')
            ->orderBy('position')
            ->get();

        // Task-Statistik (Tab 1)
        $taskStats = $project->tasks()
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // Zeiteinträge (Tab 3)
        $timeEntries = $project->timeEntries()
            ->with(['user:id,name', 'task:id,title'])
            ->orderByDesc('date')
            ->limit(100)
            ->get();

        // Zeit-Aggregationen (Tab 1 + 3)
        $totalMinutes = $project->timeEntries()->sum('minutes');
        $monthMinutes = $project->timeEntries()
            ->where('date', '>=', now()->startOfMonth())
            ->sum('minutes');
        $myMinutes = $project->timeEntries()
            ->where('user_id', Auth::id())
            ->sum('minutes');

        // Nächster Milestone für Übersicht
        $nextMilestone = $project->milestones()
            ->where('is_completed', false)
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->first();

        $recurrenceRules = RecurrenceRule::query()
            ->where('project_id', $project->id)
            ->orderBy('name')
            ->get();

        return view('projects.show', [
            'project' => $project,
            'entries' => $entries,
            'milestones' => $milestones,
            'topTasks' => $topTasks,
            'taskStats' => $taskStats,
            'timeEntries' => $timeEntries,
            'totalMinutes' => (int) $totalMinutes,
            'monthMinutes' => (int) $monthMinutes,
            'myMinutes' => (int) $myMinutes,
            'nextMilestone' => $nextMilestone,
            'timesheets' => $project->timesheets()->with('user:id,name')->latest('work_date')->limit(50)->get(),
            'recurrenceRules' => $recurrenceRules,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', Project::class);

        return view('projects._form_dialog', [
            'project' => null,
            'isDialog' => true,
            'teams' => $this->orgTeams(),
            'orgUsers' => $this->orgUsers(),
            'assignedTeamIds' => [],
            'assignedMemberIds' => [],
        ]);
    }

    public function store(SaveProjectRequest $request): RedirectResponse {
        Gate::authorize('create', Project::class);

        $data = $request->validated();
        $teamIds = $data['team_ids'] ?? [];
        $memberIds = $data['member_ids'] ?? [];
        unset($data['team_ids'], $data['member_ids']);

        $project = Project::create($data + ['created_by' => Auth::id()]);
        $this->syncTeamsAndMembers($project, $teamIds, $memberIds);

        return redirect()->route('projects.show', $project)
            ->with('success', __('Projekt angelegt.'));
    }

    public function edit(Request $request, Project $project): View {
        Gate::authorize('update', $project);

        return view('projects._form_dialog', [
            'project' => $project,
            'isDialog' => true,
            'teams' => $this->orgTeams(),
            'orgUsers' => $this->orgUsers(),
            'assignedTeamIds' => $project->teams()->pluck('teams.id')->all(),
            'assignedMemberIds' => $project->members()->pluck('users.id')->all(),
        ]);
    }

    public function update(SaveProjectRequest $request, Project $project): RedirectResponse {
        Gate::authorize('update', $project);

        $data = $request->validated();
        $teamIds = $data['team_ids'] ?? null;
        $memberIds = $data['member_ids'] ?? null;
        unset($data['team_ids'], $data['member_ids']);

        $project->update($data);
        $this->syncTeamsAndMembers($project, $teamIds, $memberIds);

        return redirect()->route('projects.show', $project)
            ->with('success', __('Projekt aktualisiert.'));
    }

    public function destroy(Project $project): RedirectResponse {
        Gate::authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', __('Projekt gelöscht.'));
    }

    /**
     * Projekt-Zeitstrahl: Aufgaben als Balken über ihren Bearbeitungszeitraum
     * (Startdatum – Deadline), gruppiert nach Bearbeiter, plus Milestone-Marker.
     */
    public function planning(Project $project): View {
        Gate::authorize('view', $project);

        $tasks = $project->tasks()
            ->with(['assignees:id,name'])
            ->orderBy('start_date')
            ->orderBy('due_date')
            ->get();
        $milestones = $project->milestones()->whereNotNull('due_date')->orderBy('due_date')->get();

        return view('projects.planning', [
            'project' => $project,
            'timeline' => $this->buildTimeline($tasks, $milestones, $project),
        ]);
    }

    /**
     * Berechnet die Zeitstrahl-Daten (Achse in Wochen, Balken in Prozent-Offsets).
     *
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, \App\Models\Milestone>  $milestones
     * @return array<string, mixed>
     */
    private function buildTimeline(Collection $tasks, Collection $milestones, Project $project): array {
        $dates = collect();
        foreach ($tasks as $t) {
            $t->start_date && $dates->push(CarbonImmutable::parse($t->start_date));
            $t->due_date && $dates->push(CarbonImmutable::parse($t->due_date));
        }
        foreach ($milestones as $m) {
            $dates->push(CarbonImmutable::parse($m->due_date));
        }
        $project->starts_on && $dates->push(CarbonImmutable::parse($project->starts_on));
        $project->ends_on && $dates->push(CarbonImmutable::parse($project->ends_on));

        $from = ($dates->min() ? CarbonImmutable::parse($dates->min()) : CarbonImmutable::now())->startOfWeek();
        $to = ($dates->max() ? CarbonImmutable::parse($dates->max()) : $from->addWeeks(8))->endOfWeek();
        if ($to->lessThanOrEqualTo($from)) {
            $to = $from->addWeeks(8);
        }
        $fromTs = $from->getTimestamp();
        $span = max(1, $to->getTimestamp() - $fromTs);
        $totalDays = max(1, (int) round($span / 86400));

        $pct = static fn(CarbonImmutable $d): float => max(0.0, min(100.0, ($d->getTimestamp() - $fromTs) / $span * 100));
        $daysFromStart = static fn(CarbonImmutable $d): int => (int) round(($d->getTimestamp() - $fromTs) / 86400);

        $weeks = [];
        for ($cursor = $from; $cursor->lessThan($to); $cursor = $cursor->addWeek()) {
            $weeks[] = ['label' => $cursor->isoFormat('DD.MM.'), 'offsetPct' => $pct($cursor)];
        }

        $today = CarbonImmutable::today();

        $rowFor = function (Task $t) use ($pct, $daysFromStart): array {
            $start = $t->start_date ? CarbonImmutable::parse($t->start_date) : ($t->due_date ? CarbonImmutable::parse($t->due_date) : null);
            $end = $t->due_date ? CarbonImmutable::parse($t->due_date) : $start;
            $dated = $start !== null;
            $offset = $dated ? $pct($start) : 0.0;
            $width = ($dated && $end !== null) ? max(2.0, $pct($end) - $offset) : 0.0;

            return [
                'task' => $t,
                'dated' => $dated,
                'offsetPct' => $offset,
                'widthPct' => $width,
                'editable' => Gate::allows('update', $t),
                'startOffsetDays' => $dated ? max(0, $daysFromStart($start)) : 0,
                'durationDays' => ($dated && $end !== null) ? max(0, $daysFromStart($end) - $daysFromStart($start)) : 0,
                'startIso' => $t->start_date?->toDateString(),
                'dueIso' => $t->due_date?->toDateString(),
            ];
        };

        // Bei Mehrfach-Zuweisung erscheint eine Aufgabe in der Zeile jedes
        // Bearbeiters; Aufgaben ohne Bearbeiter sammeln sich unter „Ohne Zuweisung".
        $buckets = [];
        foreach ($tasks as $t) {
            $row = $rowFor($t);
            $names = $t->assignees->pluck('name');
            if ($names->isEmpty()) {
                $names = collect([(string) __('Ohne Zuweisung')]);
            }
            foreach ($names as $name) {
                $buckets[(string) $name][] = $row;
            }
        }
        ksort($buckets);
        $groups = collect($buckets)
            ->map(fn(array $rows, string $label): array => ['label' => $label, 'tasks' => collect($rows)])
            ->values();

        $milestoneMarkers = $milestones->map(fn($m): array => [
            'milestone' => $m,
            'offsetPct' => $pct(CarbonImmutable::parse($m->due_date)),
        ])->values();

        return [
            'from' => $from,
            'to' => $to,
            'fromIso' => $from->toDateString(),
            'totalDays' => $totalDays,
            'weeks' => $weeks,
            'groups' => $groups,
            'milestones' => $milestoneMarkers,
            'todayPct' => ($today->betweenIncluded($from, $to)) ? $pct($today) : null,
        ];
    }

    /**
     * Aktive Teams der aktuellen Organisation (für die Projekt-Zuordnung).
     *
     * @return Collection<int, Team>
     */
    private function orgTeams(): Collection {
        return Team::query()->active()->orderBy('name')->get(['id', 'name', 'color'])->toBase();
    }

    /**
     * Benutzer der aktuellen Organisation (für Einzelmitglieder).
     *
     * @return Collection<int, User>
     */
    private function orgUsers(): Collection {
        /** @var User $auth */
        $auth = Auth::user();

        return User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $auth->organization_id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toBase();
    }

    /**
     * Synchronisiert Teams und Einzelmitglieder eines Projekts, jeweils auf die
     * eigene Organisation eingeschränkt. `null` lässt die Zuordnung unverändert.
     *
     * @param  list<int>|null  $teamIds
     * @param  list<int>|null  $memberIds
     */
    private function syncTeamsAndMembers(Project $project, ?array $teamIds, ?array $memberIds): void {
        /** @var User $auth */
        $auth = Auth::user();

        if ($teamIds !== null) {
            // Team ist org-scoped → whereIn liefert nur Teams der eigenen Org.
            $valid = Team::query()->whereIn('id', $teamIds)->pluck('id')->all();
            $project->teams()->sync($valid);
        }

        if ($memberIds !== null) {
            $valid = User::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $memberIds)
                ->where('organization_id', $auth->organization_id)
                ->pluck('id')
                ->all();
            $project->members()->sync($valid);
        }
    }
}
