<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilitySiteController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sustainability;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Sustainability\{SustainabilityAssessment, SustainabilitySite};
use App\Services\Sustainability\SiteBenchmarkService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** Standorte und Standort-Benchmarking (MVP-929). */
class SustainabilitySiteController extends Controller {
    use ResolvesCurrentOrganization;

    public function benchmark(Request $request, SiteBenchmarkService $benchmark, \App\Services\Sustainability\CustomerGroupBenchmarkService $groups): View {
        Gate::authorize('viewAny', SustainabilityAssessment::class);
        $year = max(2000, min(2100, $request->integer('year', (int) now()->year)));

        return view('sustainability.benchmark', [
            'year' => $year,
            'rows' => $benchmark->benchmark((int) $this->currentOrganization()->id, $year),
            'groupRows' => $groups->benchmark((int) $this->currentOrganization()->id, $year),
            'canManage' => Gate::allows('create', SustainabilityAssessment::class),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', SustainabilityAssessment::class);
        SustainabilitySite::query()->create($this->validated($request) + [
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => (int) Auth::id(),
        ]);

        return back()->with('success', __('sustainability.site.flash.saved'));
    }

    public function update(Request $request, SustainabilitySite $site): RedirectResponse {
        Gate::authorize('create', SustainabilityAssessment::class);
        $site->update($this->validated($request));

        return back()->with('success', __('sustainability.site.flash.saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:40'],
            'area_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'headcount' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
