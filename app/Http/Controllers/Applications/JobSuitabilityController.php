<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobSuitabilityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Applications;

use App\Http\Controllers\Controller;
use App\Models\Applications\{JobApplication, JobRequisition};
use App\Models\Learning\{Competency, CompetencyRequirement};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Applications\SuitabilityService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Eignungsmatrix (MVP-924): Kompetenz-Soll der Stelle, Einschätzung je Bewerbung. */
class JobSuitabilityController extends Controller {
    public function __construct(private readonly SuitabilityService $suitability) {}

    public function show(JobRequisition $requisition): View {
        Gate::authorize('view', $requisition);

        return view('applications.recruiting.requisitions.suitability', [
            'requisition' => $requisition,
            'matrix' => $this->suitability->matrix($requisition),
            'competencies' => Competency::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'max_level']),
        ]);
    }

    public function storeRequirement(Request $request, JobRequisition $requisition): RedirectResponse {
        Gate::authorize('update', $requisition);
        $competency = $this->competency($request);
        $data = $request->validate(['required_level' => ['required', 'integer', 'min:1', 'max:10']]);
        $this->suitability->setRequirement($requisition, $competency, (int) $data['required_level']);

        return back()->with('success', __('recruiting.suitability.flash.requirement'));
    }

    public function destroyRequirement(JobRequisition $requisition, CompetencyRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $requisition);
        $this->suitability->removeRequirement($requisition, $requirement);

        return back()->with('success', __('recruiting.suitability.flash.requirement_removed'));
    }

    public function rate(Request $request, JobApplication $application): RedirectResponse {
        Gate::authorize('update', $application);
        abort_if($application->anonymized_at !== null, 404);
        $competency = $this->competency($request);
        $data = $request->validate([
            'level' => ['required', 'integer', 'min:0', 'max:10'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->suitability->rate($application, $competency, (int) $data['level'], $data['note'] ?? null, $request->user() ?? abort(401));

        return back()->with('success', __('recruiting.suitability.flash.rated'));
    }

    private function competency(Request $request): Competency {
        $request->merge(['competency_id' => Sqid::decodeOrNumeric(Competency::class, $request->string('competency_id')->toString())]);
        $request->validate(['competency_id' => ['required', 'integer', new ExistsInCurrentOrganization('competencies')]]);

        return Competency::query()->findOrFail((int) $request->input('competency_id'));
    }
}
