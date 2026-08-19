<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetEntryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Http\Controllers\Concerns\ProvidesTimeEntryTagPicker;
use App\Http\Requests\SaveTimesheetEntryRequest;
use App\Models\{Project, TimeEntry, Timesheet};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class TimesheetEntryController extends Controller {
    use ProvidesTimeEntryTagPicker;

    public function create(Project $project, Timesheet $timesheet): View {
        Gate::authorize('update', $timesheet);
        $tasks = $project->tasks()->orderBy('title')->get(['id', 'title']);

        return view('timesheets._entry_form_dialog', [
            'project' => $project,
            'timesheet' => $timesheet,
            'tasks' => $tasks,
            'recentDescriptions' => $this->recentDescriptions($project, (int) $timesheet->user_id),
        ] + $this->tagPickerData());
    }

    public function store(Project $project, Timesheet $timesheet, SaveTimesheetEntryRequest $request): RedirectResponse {
        Gate::authorize('update', $timesheet);

        $data = $request->validated();
        // Tags sind keine Spalten — vor dem Mass-Assignment herauslösen.
        [$tagIds, $newTags] = $this->pullTagInput($data);

        /** @var TimeEntry $entry */
        $entry = $timesheet->entries()->create($data + [
            'user_id' => Auth::id(),
            'project_id' => $project->id,
            'organization_id' => $project->organization_id,
            'date' => $data['date'] ?? ($data['started_at'] ?? $timesheet->work_date),
            'kind' => $data['kind'] ?? TimeEntryKind::Work->value,
        ]);
        $entry->syncTagsFromInput($tagIds, $newTags);

        return back()->with('success', __('Zeile hinzugefügt.'));
    }

    public function update(Project $project, Timesheet $timesheet, TimeEntry $entry, SaveTimesheetEntryRequest $request): RedirectResponse {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);

        $data = $request->validated();
        [$tagIds, $newTags] = $this->pullTagInput($data);

        $entry->update($data);
        // Nur synchronisieren, wenn das Formular den Tag-Picker überhaupt
        // mitschickt — sonst würde ein Update ohne Tag-Felder (API, Alt-Formular)
        // die vorhandenen Tags stillschweigend abräumen.
        if ($request->has('new_tags') || $request->has('tag_ids')) {
            $entry->syncTagsFromInput($tagIds, $newTags);
        }

        return back()->with('success', __('Zeile aktualisiert.'));
    }

    public function destroy(Project $project, Timesheet $timesheet, TimeEntry $entry): RedirectResponse {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);

        $entry->delete();

        return back()->with('success', __('Zeile gelöscht.'));
    }

    /**
     * Auswahldialog für bereits erfasste Zeiten desselben Tages (Stoppuhr,
     * Heute-Leiste, Toggl-/Kimai-Import), die noch an keinem Zettel hängen.
     */
    public function adoptForm(Project $project, Timesheet $timesheet): View {
        Gate::authorize('update', $timesheet);

        return view('timesheets._adopt_dialog', [
            'project' => $project,
            'timesheet' => $timesheet,
            'candidates' => TimeEntry::query()->adoptableFor($timesheet)->with('task:id,title')->get(),
        ]);
    }

    public function adopt(Project $project, Timesheet $timesheet, Request $request): RedirectResponse {
        Gate::authorize('update', $timesheet);

        $ids = $request->collect('entry_ids')
            ->map(fn ($raw): ?int => Sqid::decodeOrNumeric(TimeEntry::class, $raw))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return back()->with('warning', __('Keine Zeiten ausgewählt.'));
        }

        // Auswahl gegen die Kandidatenmenge filtern statt gegen die reine ID:
        // eine untergeschobene ID könnte sonst einen fremden Eintrag anhängen.
        $entries = TimeEntry::query()->adoptableFor($timesheet)->whereKey($ids)->get();

        foreach ($entries as $entry) {
            // Einzeln speichern statt Massen-Update: nur so laufen Audit-Trail
            // und der Observer, der die Zettel-Summen neu rechnet.
            $entry->timesheet_id = $timesheet->id;
            $entry->save();
        }

        if ($entries->isEmpty()) {
            return back()->with('warning', __('Keine Zeiten ausgewählt.'));
        }

        return back()->with('success', trans_choice(
            ':count Zeit übernommen.|:count Zeiten übernommen.',
            $entries->count(),
            ['count' => $entries->count()],
        ));
    }

    /**
     * Zuletzt vom Nutzer für dieses Projekt vergebene Buchungstexte — Vorlage
     * für das Beschreibungsfeld, analog zum Quick-Pick der Heute-Leiste.
     *
     * @return array<int, string>
     */
    private function recentDescriptions(Project $project, int $userId, int $limit = 12): array {
        return TimeEntry::query()
            ->where('user_id', $userId)
            ->where('project_id', $project->id)
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(100)
            ->pluck('description')
            ->map(fn ($description): string => trim((string) $description))
            ->filter()
            ->unique(fn (string $description): string => mb_strtolower($description))
            ->take($limit)
            ->values()
            ->all();
    }
}
