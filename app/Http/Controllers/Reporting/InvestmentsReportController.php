<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentsReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase, InvestmentDeviation};
use App\Models\User;
use App\Services\Investments\InvestmentService;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Investitionsberichte (Feature 069, MVP-208): Pipeline, Budgetauslastung
 * (genehmigt/gebunden/Ist), offene Freigaben, Abweichungen — Drilldown
 * bis zur Akte, CSV-Export.
 */
class InvestmentsReportController extends Controller {
    use WritesReportCsv;

    public function index(Request $request, InvestmentService $investments): View|Response {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can(P::InvestmentViewAny->value), 403);

        $pipeline = [];
        $rows = [];
        $totals = ['approved' => 0.0, 'committed' => 0.0, 'actual' => 0.0];

        foreach (InvestmentCase::query()->with(['costCenter'])->orderByDesc('id')->get() as $case) {
            $pipeline[(string) $case->status] = ($pipeline[(string) $case->status] ?? 0) + 1;
            $projection = $investments->projection($case);
            if ($projection['approved'] > 0 || $projection['actual'] > 0 || $projection['committed'] > 0) {
                $rows[] = ['case' => $case, 'projection' => $projection];
                $totals['approved'] += $projection['approved'];
                $totals['committed'] += $projection['committed'];
                $totals['actual'] += $projection['actual'];
            }
        }

        $openApprovals = InvestmentBudgetRequest::query()->where('status', 'in_approval')->count();
        $openDeviations = InvestmentDeviation::query()->where('status', 'open')->count();

        if ($request->query('export') === 'csv') {
            $csv = [['Akte', 'Status', 'Kostenstelle', 'Genehmigt €', 'Gebunden €', 'Ist €', 'Rest €']];
            foreach ($rows as $row) {
                $csv[] = [
                    $row['case']->title,
                    $row['case']->status,
                    $row['case']->costCenterDisplay() ?? '',
                    number_format($row['projection']['approved'], 2, '.', ''),
                    number_format($row['projection']['committed'], 2, '.', ''),
                    number_format($row['projection']['actual'], 2, '.', ''),
                    $row['projection']['remaining'] !== null ? number_format($row['projection']['remaining'], 2, '.', '') : '',
                ];
            }

            return $this->csvWithMetadata($csv, 'investments.csv', 'investments', []);
        }

        return view('reports.investments', [
            'pipeline' => $pipeline,
            'rows' => $rows,
            'totals' => $totals,
            'openApprovals' => $openApprovals,
            'openDeviations' => $openDeviations,
        ]);
    }
}
