<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryBarController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Diary\Mode;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Http\Controllers\Concerns\BuildsTimeEntryOptions;
use App\Http\Requests\QuickTimeEntryRequest;
use App\Models\{DiaryEntry, Project, Task, TimeEntry, User};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, RedirectResponse};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Support\Str;

/**
 * Eingabeleiste auf „Heute" (Toggl-artig): manuelle Buchung (Dauer oder
 * Von/Bis) direkt mit Projektwahl, plus Options-Endpunkt für die vom
 * gewählten Projekt abhängigen Aufgaben-/Auftrags-Selects.
 */
class TimeEntryBarController extends Controller {
    use BuildsTimeEntryOptions;

    public function store(QuickTimeEntryRequest $request): RedirectResponse {
        Gate::authorize('create', TimeEntry::class);

        /** @var User $user */
        $user = Auth::user();
        $data = $request->validated();

        /** @var Project $project */
        $project = Project::query()->findOrFail((int) $data['project_id']);

        // Aufgabe muss zum gewählten Projekt gehören (Org-Scope prüft die Rule).
        if (($data['task_id'] ?? null) !== null) {
            abort_unless(
                Task::query()->where('project_id', $project->id)->whereKey((int) $data['task_id'])->exists(),
                404,
            );
        }

        $attributes = [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'organization_id' => $project->organization_id,
            'kind' => TimeEntryKind::Work,
            'task_id' => $data['task_id'] ?? null,
            'diary_entry_id' => $data['diary_entry_id'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        $isRange = filled($data['started_at'] ?? null) && filled($data['ended_at'] ?? null);

        if ($isRange) {
            // minutes + date leitet der TimeEntry-saving-Hook aus der Spanne ab;
            // die Werte kommen bereits als UTC-Strings aus dem FormRequest.
            $attributes['started_at'] = $data['started_at'];
            $attributes['ended_at'] = $data['ended_at'];
            $attributes['break_minutes'] = (int) ($data['break_minutes'] ?? 0);
        } else {
            $attributes['minutes'] = (int) $data['minutes'];
            $attributes['date'] = CarbonImmutable::parse((string) $data['date'])->startOfDay();
        }

        // Der gewählte lokale Kalendertag bestimmt die Rücksprung-Ansicht —
        // nicht der UTC-Tag von started_at (kann beim Umrechnen abweichen).
        $dateString = CarbonImmutable::parse((string) $data['date'])->toDateString();

        TimeEntry::create($attributes);

        $redirect = redirect()->route('today.show', ['date' => $dateString])
            ->with('status', __('Zeit auf „:project" gebucht.', ['project' => $project->name]));

        if ($isRange) {
            // Ketten-Erfassung: die nächste Buchung setzt dort an, wo diese
            // endet — Datum UND Startzeit werden in der Leiste vorbelegt
            // (läuft der Eintrag über Mitternacht, springt das Datum auf den
            // Folgetag). Flash-Werte gelten nur für den nächsten Request.
            $endLocal = CarbonImmutable::parse((string) $data['ended_at'], 'UTC')->setTimezone(Tz::current());
            $redirect->with('entryBar.nextDate', $endLocal->toDateString())
                ->with('entryBar.nextStart', $endLocal->format('H:i'));
        }

        return $redirect;
    }

    /**
     * Projektabhängige Auswahloptionen für die Leiste (Fetch bei Projektwechsel).
     */
    public function options(Project $project): JsonResponse {
        Gate::authorize('create', TimeEntry::class);

        return response()->json([
            'tasks' => $this->taskOptions($project)
                ->map(fn(Task $t): array => ['id' => $t->sqid, 'title' => (string) $t->title])
                ->values(),
            'diaryEntries' => $this->diaryOptions($project)
                ->map(fn(DiaryEntry $d): array => ['id' => $d->sqid, 'label' => $this->diaryLabel($d)])
                ->values(),
        ]);
    }

    /** Anzeige-Label eines Auftrags (wie im Erfassungs-Dialog). */
    private function diaryLabel(DiaryEntry $entry): string {
        $label = $entry->title ?: Str::limit((string) $entry->content, 60);
        if ($entry->mode !== Mode::Fixed) {
            $label .= ' · ' . $entry->modeLabel();
        }

        return $label;
    }
}
