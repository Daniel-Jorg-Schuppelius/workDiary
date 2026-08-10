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
use App\Http\Requests\{ReassignTimeEntriesRequest, SaveTimeEntryRequest};
use App\Models\{Project, TimeEntry};
use App\Models\User;
use App\Services\Billing\AgreementRateResolver;
use App\Services\Classification\ClassificationResolver;
use App\Services\Flextime\CoreTimeValidator;
use App\Services\SqidEncoder;
use App\Services\Timekeeping\TimeEntryReassignService;
use Illuminate\Http\{RedirectResponse, Request};
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
            'travelFlatMinutes' => $this->travelFlatMinutes($project),
            'isDialog' => true,
        ] + $this->classificationOptions($project) + $this->tagPickerData());
    }

    public function store(Project $project, SaveTimeEntryRequest $request): RedirectResponse {
        Gate::authorize('create', TimeEntry::class);

        $data = $this->applyTravelOverride($request->validated());
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
            'travelFlatMinutes' => $this->travelFlatMinutes($project),
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

    /**
     * Anfahrtspauschale der Kundenkondition (Feature 098) für den Dialog;
     * 0 = der Kunde führt keine, das Feld bleibt ausgeblendet.
     */
    private function travelFlatMinutes(Project $project): int {
        $agreement = app(AgreementRateResolver::class)->agreementFor($project->customer_id);

        return $agreement === null ? 0 : $agreement->travel_minutes_per_entry;
    }

    /**
     * Leeres Anfahrtsfeld = Automatik aus der Kondition, ein Wert = bewusste
     * Übersteuerung, die reapplyRates() danach in Ruhe lässt.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyTravelOverride(array $data): array {
        $manual = ($data['billing_travel_minutes'] ?? null) !== null;
        if (! $manual) {
            unset($data['billing_travel_minutes']);
        }

        return $data + ['billing_travel_manual' => $manual];
    }

    public function update(Project $project, TimeEntry $timeEntry, SaveTimeEntryRequest $request): RedirectResponse {
        Gate::authorize('update', $timeEntry);

        $data = $this->applyTravelOverride($request->validated());
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

    /**
     * Vorschau-Dialog der Massen-Neuzuordnung (MVP-508): Zusammenfassung der
     * Auswahl, gesperrte Einträge mit Grund, Zielbenutzer-Auswahl.
     */
    public function reassignDialog(Project $project, Request $request): View {
        abort_unless($this->canReassign(), 403);

        $encoder = app(SqidEncoder::class);
        $ids = array_map(
            static fn($sqid): ?int => is_string($sqid) && $sqid !== '' ? $encoder->decode(TimeEntry::class, $sqid) : null,
            (array) $request->query('ids', []),
        );

        $preflight = app(TimeEntryReassignService::class)->preflight($project, $ids);

        return view('projects._time_reassign_dialog', [
            'project' => $project,
            'entries' => $preflight['entries'],
            'blocked' => $preflight['blocked'],
            'missing' => $preflight['missing'],
            'targets' => $this->reassignTargets($project),
            'isDialog' => true,
        ]);
    }

    /** Führt die Massen-Neuzuordnung aus (transaktional, siehe Service). */
    public function reassign(Project $project, ReassignTimeEntriesRequest $request): RedirectResponse {
        $data = $request->validated();

        /** @var User $target */
        $target = User::query()->withoutGlobalScopes()->findOrFail((int) $data['target_user_id']);
        /** @var User $actor */
        $actor = $request->user();

        $count = app(TimeEntryReassignService::class)->reassign(
            $project,
            array_map(intval(...), (array) $data['ids']),
            $target,
            $actor,
        );

        return redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', __(':n Zeiteinträge :name zugeordnet.', ['n' => $count, 'name' => $target->name]));
    }

    /**
     * Portal-Veröffentlichung (MVP-511): setzt/entfernt customer_visible_at
     * je Modell — kein nacktes Mass-Update, damit Audit-Events entstehen.
     */
    public function updatePortalVisibility(Project $project, Request $request): RedirectResponse {
        $user = Auth::user();
        abort_unless($user instanceof User && ($user->isAdmin() || Gate::allows(\App\Enums\User\Permission::CustomerPortalVisibilityManage->value)), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string'],
            'mode' => ['required', 'in:publish,retract'],
        ]);

        $encoder = app(SqidEncoder::class);
        $ids = array_values(array_filter(array_map(
            static fn($sqid): ?int => is_string($sqid) ? $encoder->decode(TimeEntry::class, $sqid) : null,
            (array) $data['ids'],
        )));

        $entries = $project->timeEntries()->whereIn('id', $ids)->get();
        $publish = $data['mode'] === 'publish';
        $count = 0;
        foreach ($entries as $entry) {
            if ($publish === ($entry->customer_visible_at !== null)) {
                continue;
            }
            $entry->customer_visible_at = $publish ? now() : null;
            $entry->save();
            $count++;
        }

        return redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', $publish
                ? __(':n Zeiteinträge für das Kundenportal veröffentlicht.', ['n' => $count])
                : __(':n Zeiteinträge aus dem Kundenportal zurückgezogen.', ['n' => $count]));
    }

    /** Schreibende Massenaktion: Admin oder eigene Reassign-Permission. */
    private function canReassign(): bool {
        $user = Auth::user();

        return $user instanceof User && ($user->isAdmin() || Gate::allows('timeEntry.reassign'));
    }

    /**
     * Zielauswahl: aktive interne Benutzer derselben Organisation —
     * Portalkonten (customer_id) und deaktivierte Konten sind ausgeschlossen.
     *
     * @return Collection<int, User>
     */
    private function reassignTargets(Project $project): Collection {
        return User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $project->organization_id)
            ->whereNull('customer_id')
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
