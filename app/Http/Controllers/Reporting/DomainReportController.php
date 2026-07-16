<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Domain\DomainReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Domain-Berichte (Feature 083, MVP-395): Ablauf-/Renewal-Vorschau,
 * Kostenprognose, fehlende Zuordnung, Risiken, Reconciliation, Rechnungs-
 * abdeckung und API-Health. Autorisierung über `can:domain.viewAny`.
 */
class DomainReportController extends Controller {
    public function index(Request $request, DomainReportService $reports): View {
        $user = $request->user() ?? abort(401);
        $orgId = (int) $user->organization_id;

        return view('domain.reports.index', [
            'corridors' => $reports->expiryCorridors($orgId),
            'forecast' => $reports->renewalCostForecast($orgId),
            'unmapped' => $reports->unmapped($orgId),
            'risky' => $reports->riskyRenewalModes($orgId),
            'reconciliation' => $reports->reconciliationCount($orgId),
            'coverage' => $reports->invoiceCoverage($orgId),
            'health' => $reports->connectionHealth($orgId),
        ]);
    }
}
