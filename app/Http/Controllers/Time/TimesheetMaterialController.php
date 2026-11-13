<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetMaterialController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Time;

use App\Http\Controllers\Controller;
use App\Http\Requests\Material\{SaveDayMaterialUsageRequest, SaveMaterialUsageRequest};
use App\Models\Material\{Material, MaterialUsage};
use App\Models\Project\Project;
use App\Models\Time\Timesheet;
use App\Services\Material\MaterialUsageRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TimesheetMaterialController extends Controller {
    public function create(Project $project, Timesheet $timesheet): View {
        Gate::authorize('update', $timesheet);
        $materials = Material::query()->where('is_active', true)->orderBy('name')->get();

        return view('timesheets._material_form_dialog', compact('project', 'timesheet', 'materials'));
    }

    /** Materialzeile im selben Dialog bearbeiten (Vollscan 2026-10-21). */
    public function edit(Project $project, Timesheet $timesheet, MaterialUsage $usage): View {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $usage->timesheet_id === (int) $timesheet->id, 404);
        $materials = Material::query()->where('is_active', true)->orderBy('name')->get();

        return view('timesheets._material_form_dialog', ['project' => $project, 'timesheet' => $timesheet, 'materials' => $materials, 'usage' => $usage->loadMissing('material')]);
    }

    public function store(Project $project, Timesheet $timesheet, SaveMaterialUsageRequest $request, MaterialUsageRecorder $recorder): RedirectResponse {
        Gate::authorize('update', $timesheet);

        $usage = $recorder->record($timesheet, $request->validated(), $this->authUser());

        return back()->with('success', $this->withStock(__('Material erfasst.'), $usage));
    }

    /**
     * Material auf „Heute“ (MVP-1594): Projekt und Tag statt Zettel — der
     * offene Stundenzettel des Tages nimmt es auf. Offline läuft dasselbe
     * Formular als Befehl `material.usage`.
     */
    public function storeForDay(SaveDayMaterialUsageRequest $request, MaterialUsageRecorder $recorder): RedirectResponse {
        $data = $request->validated();
        $project = Project::query()->findOrFail((int) $data['project_id']);

        $usage = $recorder->recordForDay($this->authUser(), $project, CarbonImmutable::parse((string) $data['date']), Arr::except($data, ['project_id', 'date']));

        return back()->with('success', $this->withStock(__('Material erfasst.'), $usage));
    }

    public function update(Project $project, Timesheet $timesheet, MaterialUsage $usage, SaveMaterialUsageRequest $request, MaterialUsageRecorder $recorder): RedirectResponse {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $usage->timesheet_id === (int) $timesheet->id, 404);

        $usage = $recorder->update($usage, $request->validated(), $this->authUser());

        return back()->with('success', $this->withStock(__('Material aktualisiert.'), $usage));
    }

    public function destroy(Project $project, Timesheet $timesheet, MaterialUsage $usage, MaterialUsageRecorder $recorder): RedirectResponse {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $usage->timesheet_id === (int) $timesheet->id, 404);

        $recorder->delete($usage, $this->authUser());

        return back()->with('success', __('Material entfernt.'));
    }

    /** Meldung mit der Bestandswirkung (MVP-1679), damit eine Lagerbuchung nie unbemerkt geschieht. */
    private function withStock(string $message, MaterialUsage $usage): string {
        $notice = $usage->stockNotice();

        return $notice !== null ? $message . ' ' . $notice . '.' : $message;
    }
}
