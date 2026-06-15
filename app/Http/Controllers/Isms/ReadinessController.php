<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReadinessController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsRisk, IsmsScope};
use App\Services\Isms\ReadinessAssessmentService;
use App\Services\SqidEncoder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Reifegrad-/Readiness-Assessment (Feature 044, MVP 3): begründete
 * SELBSTEINSCHÄTZUNG der Auditbereitschaft je Geltungsbereich
 * (Ampel/Score je Domäne + Gesamteinschätzung „intern auditbereit?").
 * Reine Lesesicht über den {@see ReadinessAssessmentService}, der auf dem
 * ReadinessService aufsetzt. Autorisierung über die IsmsRiskPolicy
 * (isms.viewAny), wie das Auditbereitschafts-Dashboard.
 *
 * Niemals eine automatische Konformitätsbehauptung — das Ergebnis ist eine
 * Empfehlung/Selbsteinschätzung (046-Prinzip).
 */
class ReadinessController extends Controller {
    public function __construct(
        private readonly ReadinessAssessmentService $service,
        private readonly SqidEncoder $sqids,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsRisk::class);

        $scopes = IsmsScope::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $scope = $this->resolveScope($request->query('scope'), $scopes);

        return view('isms.readiness', [
            'scope' => $scope,
            'scopes' => $scopes,
            'assessment' => $scope === null ? null : $this->service->forScope($scope),
        ]);
    }

    /**
     * Löst den Scope-Query-Parameter (Sqid) auf — ungültige, fremde
     * (Org-Scope!) oder fehlende Werte fallen auf den Default-Scope zurück
     * (Muster DashboardController).
     *
     * @param  Collection<int, IsmsScope>  $scopes
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
