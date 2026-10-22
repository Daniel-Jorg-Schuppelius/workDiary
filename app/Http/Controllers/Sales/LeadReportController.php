<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Services\Sales\LeadReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Lead-Auswertung nach Quelle und Konversion (MVP-1296), Export im Fachrecht. */
final class LeadReportController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request, LeadReport $report): View|Response {
        Gate::authorize(Permission::CustomerViewAny->value);

        [$from, $to] = $this->resolveRangeWithDefault($request, static fn (): array => [
            CarbonImmutable::now()->subMonths(12)->startOfMonth(),
            CarbonImmutable::now()->endOfDay(),
        ]);
        $data = $report->build((int) $this->currentOrganization()->id, $from, $to);

        if (in_array($request->string('export')->toString(), ['csv', 'xlsx'], true)) {
            $rows = [[(string) __('Quelle'), (string) __('lead.report.total'), (string) __('lead.report.open'), (string) __('lead.report.converted'), (string) __('lead.report.discarded'), (string) __('lead.report.rate'), (string) __('lead.report.avgDays')]];
            foreach ($data['rows'] as $row) {
                $rows[] = [$row['source']->label(), $row['total'], $row['open'], $row['converted'], $row['discarded'], $row['rate'], $row['avg_days']];
            }

            return $this->csvWithMetadata($rows, 'leads.csv', 'lead_report', ['from' => $from->toDateString(), 'to' => $to->toDateString()], $request);
        }

        return view('leads.report', ['from' => $from, 'to' => $to] + $data);
    }

    protected function exportNeedsReportPermission(): bool {
        return false;
    }
}
