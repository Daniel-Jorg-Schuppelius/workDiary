<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoverageRequirementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoverageRequirementRequest;
use App\Http\Requests\UpdateCoverageRequirementRequest;
use App\Models\CoverageRequirement;
use App\Models\DutyPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CoverageRequirementController extends Controller {
    public function index(Request $request, DutyPlan $dutyPlan): View {
        Gate::authorize('view', $dutyPlan);
        Gate::authorize('viewAny', CoverageRequirement::class);

        $requirements = CoverageRequirement::query()
            ->with('shiftType')
            ->forPlan($dutyPlan->id)
            ->orderByRaw('specific_date IS NULL')
            ->orderBy('specific_date')
            ->orderBy('weekday')
            ->get();

        return view('coverage-requirements.index', compact('dutyPlan', 'requirements'));
    }

    public function create(DutyPlan $dutyPlan): View {
        Gate::authorize('update', $dutyPlan);
        Gate::authorize('create', CoverageRequirement::class);

        return view('coverage-requirements._form_dialog', [
            'dutyPlan' => $dutyPlan,
            'requirement' => null,
            'isEdit' => false,
        ]);
    }

    public function store(StoreCoverageRequirementRequest $request, DutyPlan $dutyPlan): RedirectResponse {
        Gate::authorize('update', $dutyPlan);
        Gate::authorize('create', CoverageRequirement::class);

        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validated();
        $data['duty_plan_id'] = $dutyPlan->id;
        $data['organization_id'] = $auth->organization_id;
        $data['created_by'] = $auth->id;
        $data['updated_by'] = $auth->id;

        CoverageRequirement::create($data);

        return redirect()
            ->route('duty-plans.coverage.index', $dutyPlan)
            ->with('success', __('Soll-Besetzung gespeichert.'));
    }

    public function edit(DutyPlan $dutyPlan, CoverageRequirement $requirement): View {
        Gate::authorize('update', $dutyPlan);
        Gate::authorize('update', $requirement);

        return view('coverage-requirements._form_dialog', [
            'dutyPlan' => $dutyPlan,
            'requirement' => $requirement,
            'isEdit' => true,
        ]);
    }

    public function update(
        UpdateCoverageRequirementRequest $request,
        DutyPlan $dutyPlan,
        CoverageRequirement $requirement,
    ): RedirectResponse {
        Gate::authorize('update', $requirement);

        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validated();
        $data['updated_by'] = $auth->id;

        $requirement->update($data);

        return redirect()
            ->route('duty-plans.coverage.index', $dutyPlan)
            ->with('success', __('Soll-Besetzung aktualisiert.'));
    }

    public function destroy(DutyPlan $dutyPlan, CoverageRequirement $requirement): RedirectResponse {
        Gate::authorize('delete', $requirement);

        $requirement->delete();

        return redirect()
            ->route('duty-plans.coverage.index', $dutyPlan)
            ->with('success', __('Soll-Besetzung gelöscht.'));
    }
}
