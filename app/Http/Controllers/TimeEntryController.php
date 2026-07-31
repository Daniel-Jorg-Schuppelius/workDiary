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

use App\Enums\Classification\ClassificationDomain;
use App\Http\Controllers\Concerns\{BuildsTimeEntryOptions, ProvidesTimeEntryTagPicker};
use App\Http\Requests\SaveTimeEntryRequest;
use App\Models\{Project, TimeEntry};
use App\Models\User;
use App\Services\Classification\ClassificationResolver;
use App\Services\Flextime\CoreTimeValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class TimeEntryController extends Controller {
    use BuildsTimeEntryOptions;
    use ProvidesTimeEntryTagPicker;

    /**
     * Projekt-Picker für die Sidebar-Aktion „Zeiteintrag". Stunden brauchen
     * immer ein Projekt — der User wählt hier zuerst eines aus und landet
     * dann im normalen Erfassungs-Dialog.
     */
    public function pick(): View {
        Gate::authorize('create', TimeEntry::class);

        return view('projects._picker_dialog', Project::pickerData() + [
            'targetRoute' => 'projects.time-entries.create',
            'title' => __('Zeiteintrag erfassen'),
            'eyebrow' => __('Zeiterfassung'),
            'icon' => 'timer',
            'description' => __('Wähle ein Projekt, auf das die Stunden gebucht werden sollen.'),
            'isDialog' => true,
        ]);
    }

    public function create(Project $project): View {
        Gate::authorize('create', TimeEntry::class);

        return view('projects._time_entry_dialog', [
            'project' => $project,
            'entry' => null,
            'tasks' => $this->taskOptions($project),
            'diaryOptions' => $this->diaryOptions($project),
            'isDialog' => true,
        ] + $this->classificationOptions($project) + $this->tagPickerData());
    }

    public function store(Project $project, SaveTimeEntryRequest $request): RedirectResponse {
        Gate::authorize('create', TimeEntry::class);

        $data = $request->validated();
        // Tags sind keine Spalten — vor dem Mass-Assignment herauslösen.
        [$tagIds, $newTags] = $this->pullTagInput($data);

        $timeEntry = $project->timeEntries()->create($data + [
            'user_id' => Auth::id(),
            'organization_id' => $project->organization_id,
        ]);
        $timeEntry->syncTagsFromInput($tagIds, $newTags);

        $redirect = redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', __('Zeiteintrag erfasst.'));

        if (($warning = $this->coreTimeWarning($timeEntry)) !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function edit(Project $project, TimeEntry $timeEntry): View {
        Gate::authorize('update', $timeEntry);

        $tasks = $project->tasks()
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('projects._time_entry_dialog', [
            'project' => $project,
            'entry' => $timeEntry->load('tags:id,name,color'),
            'tasks' => $tasks,
            'diaryOptions' => $this->diaryOptions($project, $timeEntry->diary_entry_id),
            'isDialog' => true,
        ] + $this->classificationOptions($project) + $this->tagPickerData($timeEntry));
    }

    /**
     * Nacharbeits-/Kulanzgründe (Feature 014) für den Erfassungs-Dialog.
     *
     * @return array{reworkOptions: Collection<int, \App\Models\Classification>, goodwillOptions: Collection<int, \App\Models\Classification>}
     */
    private function classificationOptions(Project $project): array {
        $resolver = app(ClassificationResolver::class);
        $orgId = (int) $project->organization_id;

        return [
            'reworkOptions' => $resolver->list($orgId, ClassificationDomain::ReworkReason),
            'goodwillOptions' => $resolver->list($orgId, ClassificationDomain::GoodwillReason),
        ];
    }

    public function update(Project $project, TimeEntry $timeEntry, SaveTimeEntryRequest $request): RedirectResponse {
        Gate::authorize('update', $timeEntry);

        $data = $request->validated();
        [$tagIds, $newTags] = $this->pullTagInput($data);

        $timeEntry->update($data);
        // Voll-ersetzend (leere Auswahl leert) — Semantik der manuellen Bearbeitung.
        $timeEntry->syncTagsFromInput($tagIds, $newTags);

        $redirect = redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', __('Zeiteintrag aktualisiert.'));

        if (($warning = $this->coreTimeWarning($timeEntry->fresh())) !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    /**
     * Nicht blockierende Kernzeit-/Rahmenzeit-/Pausen-Hinweise (Vollreview
     * W2.1) für den Speicherpfad; null = kein Hinweis (Flash bleibt leer).
     */
    private function coreTimeWarning(?TimeEntry $timeEntry): ?string {
        $owner = $timeEntry?->user;

        if ($timeEntry === null || ! $owner instanceof User) {
            return null;
        }

        $violations = app(CoreTimeValidator::class)->violations($owner, $timeEntry);

        return $violations === [] ? null : implode(' ', $violations);
    }

    public function destroy(Project $project, TimeEntry $timeEntry): RedirectResponse {
        Gate::authorize('delete', $timeEntry);

        $timeEntry->delete();

        return redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', __('Zeiteintrag gelöscht.'));
    }
}
