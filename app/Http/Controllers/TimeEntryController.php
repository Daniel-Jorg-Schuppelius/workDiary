<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveTimeEntryRequest;
use App\Models\DiaryEntry;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TimeEntryController extends Controller
{
    /**
     * Projekt-Picker für die Sidebar-Aktion „Zeiteintrag". Stunden brauchen
     * immer ein Projekt — der User wählt hier zuerst eines aus und landet
     * dann im normalen Erfassungs-Dialog.
     */
    public function pick(): View
    {
        Gate::authorize('create', TimeEntry::class);

        $projects = Project::query()
            ->where('status', Project::STATUS_ACTIVE)
            ->with('customer:id,name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'customer_id', 'parent_id', 'color']);

        // Hierarchie: Roots = ohne Parent oder Parent außerhalb des aktiven Sets
        // (verwaiste Sub-Projekte erscheinen dann selbst als Root). Kinder
        // werden in der View direkt unter dem Root in derselben Karte gerendert.
        $byId = $projects->keyBy('id');
        $childrenByParent = $projects->groupBy(fn ($p) => (int) ($p->parent_id ?? 0));
        $roots = $projects
            ->filter(fn ($p) => $p->parent_id === null || ! $byId->has($p->parent_id))
            ->values();

        // Kunden-Filter: Sentinel "0" für interne Projekte (ohne Kunde), echte
        // Kunden-IDs sonst. Wird aus der Projektmenge abgeleitet, damit der
        // Picker keine Kunden anbietet, unter denen es keine Auswahl gibt.
        $customers = $projects
            ->map(fn ($p) => $p->customer
                ? ['id' => (int) $p->customer->id, 'name' => $p->customer->name]
                : ['id' => 0, 'name' => __('Intern (ohne Kunde)')])
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('time-entries._picker_dialog', [
            'roots' => $roots,
            'childrenByParent' => $childrenByParent,
            'customers' => $customers,
            'isDialog' => true,
        ]);
    }

    public function create(Project $project): View
    {
        Gate::authorize('create', TimeEntry::class);

        $tasks = $project->tasks()
            ->where('status', '!=', Task::STATUS_DONE)
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('projects._time_entry_dialog', [
            'project' => $project,
            'entry' => null,
            'tasks' => $tasks,
            'diaryOptions' => $this->diaryOptions($project),
            'isDialog' => true,
        ]);
    }

    public function store(Project $project, SaveTimeEntryRequest $request): RedirectResponse
    {
        Gate::authorize('create', TimeEntry::class);

        $data = $request->validated();

        $project->timeEntries()->create($data + [
            'user_id' => Auth::id(),
            'organization_id' => $project->organization_id,
        ]);

        return redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', __('Zeiteintrag erfasst.'));
    }

    public function edit(Project $project, TimeEntry $timeEntry): View
    {
        Gate::authorize('update', $timeEntry);

        $tasks = $project->tasks()
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('projects._time_entry_dialog', [
            'project' => $project,
            'entry' => $timeEntry,
            'tasks' => $tasks,
            'diaryOptions' => $this->diaryOptions($project, $timeEntry->diary_entry_id),
            'isDialog' => true,
        ]);
    }

    /**
     * Aufträge, die für die Verbuchung von Stunden auf dieses Projekt sinnvoll
     * angeboten werden: alle nicht-archivierten offenen Aufträge auf dem Projekt
     * selbst plus jeder Auftrag, der bereits Stunden auf dieses Projekt gebucht
     * hat. Der aktuell verknüpfte Auftrag wird immer mit aufgenommen, damit der
     * Edit-Dialog beim Bearbeiten nichts verliert.
     *
     * @return Collection<int, DiaryEntry>
     */
    private function diaryOptions(Project $project, ?int $currentId = null): Collection
    {
        return DiaryEntry::query()
            ->select(['id', 'title', 'content', 'mode', 'status', 'project_id'])
            ->where('is_archived', false)
            ->where(function ($q) use ($project, $currentId): void {
                $q->where('project_id', $project->id)
                    ->orWhereIn('id', function ($sub) use ($project): void {
                        $sub->select('diary_entry_id')
                            ->from('time_entries')
                            ->where('project_id', $project->id)
                            ->whereNotNull('diary_entry_id');
                    });
                if ($currentId !== null) {
                    $q->orWhere('id', $currentId);
                }
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();
    }

    public function update(Project $project, TimeEntry $timeEntry, SaveTimeEntryRequest $request): RedirectResponse
    {
        Gate::authorize('update', $timeEntry);

        $timeEntry->update($request->validated());

        return redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', __('Zeiteintrag aktualisiert.'));
    }

    public function destroy(Project $project, TimeEntry $timeEntry): RedirectResponse
    {
        Gate::authorize('delete', $timeEntry);

        $timeEntry->delete();

        return redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', __('Zeiteintrag gelöscht.'));
    }
}
