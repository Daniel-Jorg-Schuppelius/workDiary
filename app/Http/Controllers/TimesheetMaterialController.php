<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetMaterialController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveMaterialUsageRequest;
use App\Models\Material;
use App\Models\MaterialUsage;
use App\Models\Project;
use App\Models\Timesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TimesheetMaterialController extends Controller
{
    public function store(Project $project, Timesheet $timesheet, SaveMaterialUsageRequest $request): RedirectResponse
    {
        Gate::authorize('update', $timesheet);

        $data = $request->validated();
        // Falls Stamm-Material gewählt: Defaults übernehmen, falls leer
        if (! empty($data['material_id'])) {
            $material = Material::find($data['material_id']);
            if ($material) {
                $data['unit_price'] = $data['unit_price'] ?? $material->default_unit_price;
                $data['tax_rate'] = $data['tax_rate'] ?? $material->tax_rate;
                $data['unit'] = $data['unit'] ?: $material->unit;
                $data['description'] = $data['description'] ?: $material->name;
            }
        }

        $timesheet->materialUsages()->create($data);

        return back()->with('success', __('Material erfasst.'));
    }

    public function update(Project $project, Timesheet $timesheet, MaterialUsage $usage, SaveMaterialUsageRequest $request): RedirectResponse
    {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $usage->timesheet_id === (int) $timesheet->id, 404);

        $usage->update($request->validated());

        return back()->with('success', __('Material aktualisiert.'));
    }

    public function destroy(Project $project, Timesheet $timesheet, MaterialUsage $usage): RedirectResponse
    {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $usage->timesheet_id === (int) $timesheet->id, 404);

        $usage->delete();

        return back()->with('success', __('Material entfernt.'));
    }
}
