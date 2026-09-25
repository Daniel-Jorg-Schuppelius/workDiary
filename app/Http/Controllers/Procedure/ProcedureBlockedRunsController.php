<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureBlockedRunsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Procedure;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\Procedure\ProcedureRun;
use App\Services\Procedure\BlockedRunsReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Gate;

/** Blockierte Prozedurläufe (Feature 026, MVP-897); Recht wie die Laufansicht. */
class ProcedureBlockedRunsController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request, BlockedRunsReport $report): View|Response {
        Gate::authorize('viewAny', ProcedureRun::class);

        [$from, $to] = $this->resolveRange($request);
        $current = $report->current();
        $periods = $report->periods($from, $to);

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            $rows = [['Sperrgrund', 'Prozedur', 'Anzahl', 'MittlereStunden', 'LaengsteStunden']];
            foreach ($periods as $row) {
                $rows[] = [$row['reason'], $row['template'], $row['count'], $row['avg_hours'], $row['max_hours']];
            }
            $filename = sprintf('prozeduren-blockiert_%s_%s.csv', $from->toDateString(), $to->toDateString());

            return $this->csvWithMetadata($rows, $filename, 'procedure-blocked', ['from' => $from->toDateString(), 'to' => $to->toDateString()], $request);
        }

        return view('procedures.runs.blocked', compact('from', 'to', 'current', 'periods'));
    }
}
