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
use App\Models\Isms\{IsmsRisk, IsmsScope};
use App\Services\Isms\ReadinessService;
use App\Services\SqidEncoder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
    public function __construct(
        private readonly ReadinessService $service,
        private readonly SqidEncoder $sqids,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsRisk::class);

        $scopes = IsmsScope::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $scope = $this->resolveScope($request->query('scope'), $scopes);

        return view('isms.dashboard', [
            'scope' => $scope,
            'scopes' => $scopes,
            'readiness' => $scope === null ? null : $this->service->forScope($scope),
        ]);
    }

    /**
     * Löst den Scope-Query-Parameter (Sqid) auf — ungültige, fremde
     * (Org-Scope!) oder fehlende Werte fallen auf den Default-Scope
     * zurück (Muster RequirementController).
     *
     * @param  Collection<int, IsmsScope>  $scopes  bereits geladene Scopes
     */
    private function resolveScope(mixed $sqid, Collection $scopes): ?IsmsScope {
        if (is_string($sqid) && $sqid !== '') {
            $id = $this->sqids->decode(IsmsScope::class, $sqid);
            $scope = $id === null ? null : $scopes->firstWhere('id', $id);

            if ($scope !== null) {
                return $scope;
            }
        }

        return $scopes->firstWhere('is_default', true) ?? $scopes->first();
    }
}
