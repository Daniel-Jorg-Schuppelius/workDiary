<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsfController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Isms\Concerns\ResolvesIsmsScope;
use App\Models\Isms\{IsmsRequirement, IsmsScope};
use App\Services\Isms\CsfReadinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * NIST-CSF-2.0-Sichten (Feature 044/046, Nachtrag NIST): reines Lesen über
 * den {@see CsfReadinessService}. „dashboard" zeigt die Abdeckung der sechs
 * CSF-Funktionen je Geltungsbereich (direkt aus der NIST-SoA oder
 * abgeleitet aus der ISO-SoA via Crosswalk); „crosswalk" zeigt die
 * CSF→ISO/IEC-27001-Zuordnung mit Abdeckung. Autorisierung wie der übrige
 * ISMS-Lesezugriff über die IsmsRequirementPolicy (isms.viewAny).
 */
class CsfController extends Controller {
    use ResolvesIsmsScope;

    public function __construct(
        private readonly CsfReadinessService $service,
    ) {}

    public function dashboard(Request $request): View {
        Gate::authorize('viewAny', IsmsRequirement::class);

        $scopes = $this->scopes();
        $scope = $this->scopeForView($request->query('scope'), $scopes);

        return view('isms.csf.dashboard', [
            'scope' => $scope,
            'scopes' => $scopes,
            'readiness' => $scope === null ? null : $this->service->forScope($scope),
        ]);
    }

    public function crosswalk(Request $request): View {
        Gate::authorize('viewAny', IsmsRequirement::class);

        $scopes = $this->scopes();
        $scope = $this->scopeForView($request->query('scope'), $scopes);

        return view('isms.csf.crosswalk', [
            'scope' => $scope,
            'scopes' => $scopes,
            'crosswalk' => $scope === null ? null : $this->service->crosswalkForScope($scope),
        ]);
    }

    /** @return Collection<int, IsmsScope> */
    private function scopes(): Collection {
        return IsmsScope::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}
