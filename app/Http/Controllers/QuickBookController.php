<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuickBookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Project, Task, TimeEntry, User};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Quick-Buchung offener Zeitblöcke auf ein Projekt (MVP-015, Rang 37).
 *
 * Ein offener Block (aus {@see \App\Services\TimeApproval\UntrackedBlockCalculator})
 * wird per Drag auf ein Projekt gezogen oder über das Fallback-Formular (ohne
 * JS) gebucht. Bei `Accept: application/json` (Drag/Ctrl+Enter) antwortet der
 * Endpunkt mit JSON, sonst mit Redirect zurück auf „Heute" — dieselbe Buchung.
 */
class QuickBookController extends Controller {
    public function store(Request $request): RedirectResponse|JsonResponse {
        Gate::authorize('create', TimeEntry::class);

        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'project' => ['required', 'string'],
            'task' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Entweder Zeitspanne (aus dem Block) ODER Dauer+Datum (Fallback).
            'started_at' => ['nullable', 'required_without:minutes', 'date'],
            'ended_at' => ['nullable', 'required_with:started_at', 'date', 'after:started_at'],
            'minutes' => ['nullable', 'required_without:started_at', 'integer', 'min:1', 'max:1440'],
            'date' => ['nullable', 'date'],
        ]);

        $project = $this->resolveProject((string) $data['project'], $user);
        $taskId = $this->resolveTaskId($data['task'] ?? null, $project);

        $attributes = [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'kind' => TimeEntryKind::Work,
            'task_id' => $taskId,
            'description' => $data['description'] ?? null,
        ];

        if (filled($data['started_at'] ?? null)) {
            // minutes + date berechnet der TimeEntry-saving-Hook aus der Spanne.
            $attributes['started_at'] = CarbonImmutable::parse((string) $data['started_at']);
            $attributes['ended_at'] = CarbonImmutable::parse((string) $data['ended_at']);
            $dateString = $attributes['started_at']->toDateString();
        } else {
            $attributes['minutes'] = (int) $data['minutes'];
            $day = filled($data['date'] ?? null) ? CarbonImmutable::parse((string) $data['date']) : CarbonImmutable::today();
            $attributes['date'] = $day->startOfDay();
            $dateString = $day->toDateString();
        }

        /** @var TimeEntry $entry */
        $entry = TimeEntry::create($attributes);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'entry' => [
                    'id' => $entry->sqid,
                    'minutes' => (int) $entry->minutes,
                    'project' => $project->name,
                    'started_at' => $entry->started_at?->toIso8601String(),
                    'ended_at' => $entry->ended_at?->toIso8601String(),
                ],
            ], 201);
        }

        return redirect()->route('today.show', ['date' => $dateString])
            ->with('status', __('Zeit auf „:project" gebucht.', ['project' => $project->name]));
    }

    /** Projekt über Sqid auflösen, strikt organisationsgescopet (404 bei fremd). */
    private function resolveProject(string $rawId, User $user): Project {
        $id = Sqid::decode(Project::class, $rawId);
        abort_if($id === null, 404);

        /** @var Project|null $project */
        $project = Project::query()
            ->where('organization_id', $user->organization_id)
            ->find($id);
        abort_unless($project instanceof Project, 404);

        return $project;
    }

    /** Optionale Aufgabe auflösen; muss zum Projekt gehören (sonst 404). */
    private function resolveTaskId(?string $rawId, Project $project): ?int {
        if ($rawId === null || $rawId === '') {
            return null;
        }

        $id = Sqid::decode(Task::class, $rawId);
        abort_if($id === null, 404);
        abort_unless(
            Task::query()->where('project_id', $project->id)->whereKey($id)->exists(),
            404,
        );

        return $id;
    }
}
