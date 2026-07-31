<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerProjectReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Customer, Project, TimeEntry};
use App\Services\Reporting\ReportFilters;
use App\Support\{Sqid, XlsxExport};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Customer × Project-Auswertung: Stunden + Umsatz pro Kunde/Projekt
 * im gewählten Zeitraum.
 *
 * Pattern angelehnt an Kimai's CustomerMonthlyProjectsController (AGPL-3.0)
 * — eigene Implementierung, kein Code-Reuse.
 */
class CustomerProjectReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$fromDate, $toDate] = $this->resolveRange($request);
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        // Mitarbeiter-Filter nur für Admins — Nicht-Admins sehen ohnehin nur eigene Zeiten.
        $filterFields = $isAdmin ? ['customer', 'project', 'user'] : ['customer', 'project'];
        $filters = $this->standardFilters($request, $filterFields, $fromDate, $toDate, scope: $scope);

        $foreignCustomerParam = $request->string('foreign_customer')->toString();
        $foreignCustomerId = Sqid::decode(\App\Models\ForeignCustomer::class, $foreignCustomerParam);

        $byProject = $this->aggregateByProject($from, $to, $scope, $userId, $filters, $foreignCustomerId);
        $bucket = $this->bucketByCustomer($byProject);
        $this->sortBuckets($bucket);

        $totalMinutes = array_sum(array_column($bucket, 'minutes'));
        $totalRate = array_sum(array_column($bucket, 'rate'));

        $exportFilters = array_merge(
            array_filter(['foreign_customer' => $foreignCustomerId]),
            $filters->toAuditArray(),
        );

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($bucket, $totalMinutes, $totalRate, $from, $to, $exportFilters, $request);
        }
        if ($request->query('export') === 'xlsx') {
            return $this->exportXlsx($bucket, $totalMinutes, $totalRate, $from, $to);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($bucket, $totalMinutes, $totalRate, $from, $to, $scope, $this->topProjectsSeries($bucket, $filters), $exportFilters, $request);
        }

        return view('reports.customer-project', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'bucket' => $bucket,
            'totalMinutes' => $totalMinutes,
            'totalRate' => $totalRate,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'foreignCustomerParam' => $foreignCustomerParam,
            'customerHoursSeries' => $this->customerHoursSeries($bucket, $filters),
            'topProjectsSeries' => $this->topProjectsSeries($bucket, $filters),
            ...$this->standardFilterOptions($filterFields, $filters),
        ]);
    }

    /**
     * @return array<int, array{minutes: int, rate: float}>
     */
    private function aggregateByProject(string $from, string $to, string $scope, int $userId, ReportFilters $filters, ?int $foreignCustomerId = null): array {
        $query = TimeEntry::query()
            ->whereBetween('date', [$from, $to])
            ->select('project_id', 'minutes', 'rate', 'user_id');
        if ($scope === 'mine') {
            $query->where('user_id', $userId);
        }
        if ($foreignCustomerId !== null) {
            $query->whereHas('project', fn($q) => $q->where('foreign_customer_id', $foreignCustomerId));
        }
        $filters->applyToTimeEntryQuery($query);

        $byProject = [];
        foreach ($query->get() as $e) {
            $pid = (int) $e->project_id;
            if (! isset($byProject[$pid])) {
                $byProject[$pid] = ['minutes' => 0, 'rate' => 0.0];
            }
            $byProject[$pid]['minutes'] += (int) $e->minutes;
            $byProject[$pid]['rate'] += ($e->rate?->toFloat() ?? 0.0);
        }

        return $byProject;
    }

    /**
     * @param  array<int, array{minutes: int, rate: float}>  $byProject
     * @return array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}>
     */
    private function bucketByCustomer(array $byProject): array {
        $projects = Project::with(['customer', 'foreignCustomer'])
            ->whereIn('id', array_keys($byProject))
            ->get()
            ->keyBy('id');

        $bucket = [];
        foreach ($byProject as $pid => $sums) {
            $project = $projects->get($pid);
            if (! $project instanceof Project) {
                continue;
            }
            $cid = $project->customer_id ?? '_none';
            if (! isset($bucket[$cid])) {
                $bucket[$cid] = [
                    'customer' => $project->customer,
                    'projects' => [],
                    'minutes' => 0,
                    'rate' => 0.0,
                ];
            }
            $bucket[$cid]['projects'][$pid] = [
                'project' => $project,
                'minutes' => $sums['minutes'],
                'rate' => $sums['rate'],
            ];
            $bucket[$cid]['minutes'] += $sums['minutes'];
            $bucket[$cid]['rate'] += $sums['rate'];
        }

        return $bucket;
    }

    /**
     * @param  array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}>  $bucket
     */
    private function sortBuckets(array &$bucket): void {
        uasort($bucket, function ($a, $b): int {
            $na = $a['customer'] instanceof Customer ? $a['customer']->name : '~~~';
            $nb = $b['customer'] instanceof Customer ? $b['customer']->name : '~~~';

            return strnatcasecmp($na, $nb);
        });
        foreach ($bucket as &$row) {
            uasort($row['projects'], fn($a, $b) => $b['minutes'] <=> $a['minutes']);
        }
        unset($row);
    }

    /**
     * Stunden je Kunde (Top 20, Pareto) — Drilldown filtert diesen Report auf den Kunden.
     *
     * @param  array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}>  $bucket
     * @return list<array{x: string, y: float, url: ?string}>
     */
    private function customerHoursSeries(array $bucket, ReportFilters $filters): array {
        $series = [];
        foreach ($bucket as $row) {
            if ($row['minutes'] <= 0) {
                continue;
            }
            $customer = $row['customer'];
            $series[] = [
                'x' => $customer instanceof Customer ? $customer->name : __('Ohne Kunde'),
                'y' => round($row['minutes'] / 60, 1),
                'url' => $customer instanceof Customer
                    ? route('reports.customer-project', array_merge($filters->toQueryParams(), [
                        'customer' => Sqid::encode(Customer::class, $customer->id),
                    ]))
                    : null,
            ];
        }
        usort($series, static fn(array $a, array $b): int => $b['y'] <=> $a['y']);

        return array_slice($series, 0, 20);
    }

    /**
     * Top-Projekte nach Stunden (Top 15) — Drilldown öffnet den
     * Projekt-Details-Report mit geerbtem Filterkontext.
     *
     * @param  array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}>  $bucket
     * @return list<array{x: string, y: float, url: string}>
     */
    private function topProjectsSeries(array $bucket, ReportFilters $filters): array {
        $series = [];
        foreach ($bucket as $row) {
            foreach ($row['projects'] as $entry) {
                if ($entry['minutes'] <= 0) {
                    continue;
                }
                $customer = $row['customer'];
                $label = $entry['project']->name
                    . ($customer instanceof Customer ? ' · ' . $customer->name : '');
                $series[] = [
                    'x' => $label,
                    'y' => round($entry['minutes'] / 60, 1),
                    'url' => route('reports.project-details', array_merge($filters->toQueryParams(), [
                        'project' => Sqid::encode(Project::class, $entry['project']->id),
                    ])),
                ];
            }
        }
        usort($series, static fn(array $a, array $b): int => $b['y'] <=> $a['y']);

        return array_slice($series, 0, 15);
    }

    /**
     * @param  array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}>  $bucket
     * @return list<list<int|float|string|null>>
     */
    private function buildRows(array $bucket, int $totalMinutes, float $totalRate): array {
        $rows = [];
        foreach ($bucket as $row) {
            $customerName = $row['customer'] instanceof Customer ? $row['customer']->name : '(Ohne Kunde)';
            foreach ($row['projects'] as $entry) {
                $foreign = $entry['project']->foreignCustomer;
                $rows[] = [
                    $customerName,
                    $foreign instanceof \App\Models\ForeignCustomer ? (string) $foreign->name : '',
                    (string) $entry['project']->name,
                    (string) ($entry['project']->number ?? ''),
                    (int) $entry['minutes'],
                    (float) $entry['rate'],
                ];
            }
        }
        $rows[] = ['Gesamt', '', '', '', $totalMinutes, (float) $totalRate];

        return $rows;
    }

    /**
     * @param  array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}>  $bucket
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $bucket, int $totalMinutes, float $totalRate, string $from, string $to, array $exportFilters, Request $request): Response {
        $filename = sprintf('kunden-projekte_%s_%s.csv', $from, $to);
        $rows = [['Kunde', 'Endkunde', 'Projekt', 'Projektnummer', 'Minuten', 'Erloes']];
        foreach ($this->buildRows($bucket, $totalMinutes, $totalRate) as $row) {
            $rows[] = array_map(static fn($v) => is_float($v) ? NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) : $v, $row);
        }

        return $this->csvWithMetadata($rows, $filename, 'customer-project', $exportFilters, $request);
    }

    /**
     * @param  array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}>  $bucket
     */
    private function exportXlsx(array $bucket, int $totalMinutes, float $totalRate, string $from, string $to): SymfonyResponse {
        $filename = sprintf('kunden-projekte_%s_%s.xlsx', $from, $to);
        $headers = ['Kunde', 'Endkunde', 'Projekt', 'Projektnummer', 'Minuten', 'Erloes'];

        return XlsxExport::streamFromArray($filename, $headers, $this->buildRows($bucket, $totalMinutes, $totalRate));
    }

    /**
     * @param  array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}>  $bucket
     * @param  list<array{x: string, y: float, url: string}>  $topProjectsSeries
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $bucket, int $totalMinutes, float $totalRate, string $from, string $to, string $scope, array $topProjectsSeries, array $exportFilters, Request $request): SymfonyResponse {
        $filename = sprintf('kunden-projekte_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.customer-project', [
            'bucket' => $bucket,
            'totalMinutes' => $totalMinutes,
            'totalRate' => $totalRate,
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Top-Projekte nach Stunden'),
                'unit' => 'h',
                'xLabel' => __('Projekt'),
                'yLabel' => __('Stunden'),
                'series' => $topProjectsSeries,
            ],
        ], $filename, request: $request, reportCode: 'customer-project', filters: $exportFilters);
    }
}
