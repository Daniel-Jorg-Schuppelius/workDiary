<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Patrol;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\Facility\Site;
use App\Models\Patrol\PatrolRoute;
use App\Services\Patrol\PatrolReport;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rundgangsbericht je Route, Objekt und Zeitraum (MVP-1292) als Seite, PDF,
 * CSV und Excel. Der Export gehört zum Fachrecht der Rundgänge.
 */
final class PatrolReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request, PatrolReport $report): View|Response {
        Gate::authorize(Permission::DispatchViewAny->value);

        [$from, $to] = $this->resolveRangeWithDefault($request, static fn (): array => [
            CarbonImmutable::now()->subDays(30)->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        ]);
        $routeId = Sqid::decode(PatrolRoute::class, $request->string('route')->toString());
        $siteId = Sqid::decode(Site::class, $request->string('site')->toString());
        $data = $report->build((int) $this->currentOrganization()->id, $from, $to, $routeId, $siteId);
        $filters = ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'route' => $routeId, 'site' => $siteId];

        $export = $request->string('export')->toString();
        if (in_array($export, ['csv', 'xlsx'], true)) {
            $rows = [[(string) __('Beginn'), (string) __('Route'), (string) __('Objekt'), (string) __('Status'), (string) __('patrol.report.expected'), (string) __('patrol.report.onTime'), (string) __('patrol.report.late'), (string) __('patrol.report.missed'), (string) __('patrol.report.note')]];
            foreach ($data['rows'] as $row) {
                $run = $row['run'];
                $rows[] = [
                    \App\Support\CarbonFmt::orgTz($run->started_at)->format('Y-m-d H:i'),
                    $run->route?->name,
                    $run->route?->site?->name,
                    $run->status->label(),
                    $row['expected'], $row['on_time'], $row['late'], $row['missed'],
                    $run->deviation_note,
                ];
            }

            return $this->csvWithMetadata($rows, 'rundgaenge.csv', 'patrol_report', $filters, $request);
        }

        $viewData = [
            'from' => $from,
            'to' => $to,
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'routes' => PatrolRoute::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->whereIn('id', PatrolRoute::query()->whereNotNull('site_id')->select('site_id'))->orderBy('name')->get(['id', 'name']),
            'filters' => ['route' => $request->string('route')->toString(), 'site' => $request->string('site')->toString()],
        ];
        if ($export === 'pdf') {
            return $this->pdfDownload('pdf.patrol-period-report', $viewData, 'rundgaenge.pdf', 'landscape', $request, 'patrol_report', $filters);
        }

        return view('patrols.report', $viewData);
    }

    protected function exportNeedsReportPermission(): bool {
        return false;
    }
}
