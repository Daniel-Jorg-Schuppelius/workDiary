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
use App\Http\Controllers\Isms\Concerns\ResolvesIsmsScope;
use App\Models\Isms\{IsmsRisk, IsmsScope};
use App\Services\Isms\ReadinessAssessmentService;
use Illuminate\Http\Request;
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
    use ResolvesIsmsScope;

    public function __construct(
        private readonly ReadinessAssessmentService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsRisk::class);

        $scopes = IsmsScope::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $scope = $this->scopeForView($request->query('scope'), $scopes);

        return view('isms.readiness', [
            'scope' => $scope,
            'scopes' => $scopes,
            'assessment' => $scope === null ? null : $this->service->forScope($scope),
        ]);
    }
}
