<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeTargetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Project;

use App\Enums\Project\ShortfallStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\SaveProjectTimeTargetsRequest;
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Services\Project\TimeTargets\ProjectTimeTargetWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Sollzeiten je Person am Projekt pflegen (Feature 194, MVP-1467). */
final class ProjectTimeTargetController extends Controller {
    public function edit(Project $project): View {
        Gate::authorize('view', $project);
        $this->ensureTargetManager();

        $default = [];
        $memberRows = [];
        $exceptions = [];
        $validFrom = null;
        $validUntil = null;
        foreach ($project->timeTargets()->with('user:id,name')->orderBy('id')->get() as $row) {
            /** @var ProjectTimeTarget $row */
            if ($row->specific_date !== null) {
                $exceptions[] = $row;
                continue;
            }
            if ($row->user_id === null) {
                $default[(int) $row->weekday] = (int) $row->target_minutes;
            } else {
                $memberRows[(int) $row->user_id][(int) $row->weekday] = (int) $row->target_minutes;
            }
            $validFrom ??= $row->valid_from?->toDateString();
            $validUntil ??= $row->valid_until?->toDateString();
        }

        return view('projects._time_targets_dialog', [
            'project' => $project,
            'members' => $project->assignableUsers()->sortBy('name')->values(),
            'default' => $default,
            'memberRows' => $memberRows,
            'exceptions' => $exceptions,
            'validFrom' => $validFrom,
            'validUntil' => $validUntil,
        ]);
    }

    public function update(Project $project, SaveProjectTimeTargetsRequest $request, ProjectTimeTargetWriter $writer): RedirectResponse {
        Gate::authorize('view', $project);
        $this->ensureTargetManager();

        // Nur zuweisbare Personen tragen ein Soll; alles andere fällt still weg.
        $assignable = array_flip($project->assignableUsers()->pluck('id')->map(static fn ($id): int => (int) $id)->all());
        $members = array_intersect_key($request->memberMinutes(), $assignable);
        $exceptions = array_values(array_filter(
            $request->exceptionRows(),
            static fn (array $row): bool => $row['user_id'] === null || isset($assignable[$row['user_id']]),
        ));

        $writer->sync(
            $project,
            $request->defaultMinutes(),
            $members,
            $exceptions,
            $request->filled('valid_from') ? (string) $request->input('valid_from') : null,
            $request->filled('valid_until') ? (string) $request->input('valid_until') : null,
            $this->authUser(),
        );

        return redirect()
            ->route('projects.show', ['project' => $project, 'tab' => 'time'])
            ->with('success', __('Sollzeiten gespeichert.'));
    }

    /** Befund als gesehen bestätigen (Feature 194, MVP-1469); die Nachbuchung erledigt ihn weiterhin. */
    public function acknowledge(Project $project, ProjectTimeShortfall $shortfall): RedirectResponse {
        Gate::authorize('view', $project);
        $this->ensureTargetManager();
        abort_unless((int) $shortfall->project_id === (int) $project->id, 404);
        if ($shortfall->status === ShortfallStatus::Open) {
            $shortfall->update(['status' => ShortfallStatus::Acknowledged, 'acknowledged_by' => $this->authUser()->id, 'acknowledged_at' => now()]);
        }

        return redirect()
            ->route('projects.show', ['project' => $project, 'tab' => 'time', 'show' => 'shortfalls'])
            ->with('success', __('Befund bestätigt.'));
    }

    private function ensureTargetManager(): void {
        $user = $this->authUser();
        abort_unless($user->isAdmin() || $user->can(Permission::ProjectTargetsManage->value), 403);
    }
}
