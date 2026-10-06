<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Isms\Concerns\ResolvesIsmsScope;
use App\Models\Isms\{IsmsRisk, IsmsScope};
use App\Services\Isms\ReadinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Auditbereitschafts-Dashboard (Feature 044, MVP 1): KPI-Kacheln und
 * kompakte Drill-down-Listen je Geltungsbereich (Scope-Wechsler, Muster
 * Anforderungen-/Zertifizierungen-Seite) — reine Lesesicht über den
 * {@see ReadinessService}. Autorisierung über die IsmsRiskPolicy
 * (isms.viewAny), wie der übrige ISMS-Lesezugriff.
 */
class DashboardController extends Controller {
    use ResolvesIsmsScope;

    public function __construct(
        private readonly ReadinessService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsRisk::class);

        $scopes = IsmsScope::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $scope = $this->scopeForView($request->query('scope'), $scopes);

        return view('isms.dashboard', [
            'scope' => $scope,
            'scopes' => $scopes,
            'readiness' => $scope === null ? null : $this->service->forScope($scope),
        ]);
    }
}
