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
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, WritesReportCsv};
use App\Models\{Customer, Project, TimeEntry};
use App\Support\XlsxExport;
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
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$fromDate, $toDate] = $this->globalDateRangeBounds();
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $foreignCustomerId = \App\Support\Sqid::decode(\App\Models\ForeignCustomer::class, (string) $request->string('foreign_customer')->toString());

        $byProject = $this->aggregateByProject($from, $to, $scope, $userId, $foreignCustomerId);
        $bucket = $this->bucketByCustomer($byProject);
        $this->sortBuckets($bucket);

        $totalMinutes = array_sum(array_column($bucket, 'minutes'));
        $totalRate = array_sum(array_column($bucket, 'rate'));

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($bucket, $totalMinutes, $totalRate, $from, $to, $scope, $foreignCustomerId, $request);
        }
        if ($request->query('export') === 'xlsx') {
            return $this->exportXlsx($bucket, $totalMinutes, $totalRate, $from, $to);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($bucket, $totalMinutes, $totalRate, $from, $to, $scope, $foreignCustomerId, $request);
        }

        return view('reports.customer-project', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'bucket' => $bucket,
            'totalMinutes' => $totalMinutes,
            'totalRate' => $totalRate,
        ]);
    }
    /**
     * @return array<int, array{minutes: int, rate: float}>
     */
    private function aggregateByProject(string $from, string $to, string $scope, int $userId, ?int $foreignCustomerId = null): array {
        $query = TimeEntry::query()
            ->whereBetween('date', [$from, $to])
            ->select('project_id', 'minutes', 'rate', 'user_id');
        if ($scope === 'mine') {
            $query->where('user_id', $userId);
        }
        if ($foreignCustomerId !== null) {
            $query->whereHas('project', fn($q) => $q->where('foreign_customer_id', $foreignCustomerId));
        }

        $byProject = [];
        foreach ($query->get() as $e) {
            $pid = (int) $e->project_id;
            if (! isset($byProject[$pid])) {
                $byProject[$pid] = ['minutes' => 0, 'rate' => 0.0];
            }
            $byProject[$pid]['minutes'] += (int) $e->minutes;
            $byProject[$pid]['rate'] += (float) $e->rate;
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
     */
    private function exportCsv(array $bucket, int $totalMinutes, float $totalRate, string $from, string $to, string $scope, ?int $foreignCustomerId, Request $request): Response {
        $filename = sprintf('kunden-projekte_%s_%s.csv', $from, $to);
        $rows = [['Kunde', 'Endkunde', 'Projekt', 'Projektnummer', 'Minuten', 'Erloes']];
        foreach ($this->buildRows($bucket, $totalMinutes, $totalRate) as $row) {
            $rows[] = array_map(static fn($v) => is_float($v) ? NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) : $v, $row);
        }

        return $this->csvWithMetadata($rows, $filename, 'customer-project', ['from' => $from, 'to' => $to, 'scope' => $scope, 'foreign_customer' => $foreignCustomerId], $request);
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
     */
    private function exportPdf(array $bucket, int $totalMinutes, float $totalRate, string $from, string $to, string $scope, ?int $foreignCustomerId, Request $request): SymfonyResponse {
        $filename = sprintf('kunden-projekte_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.customer-project', [
            'bucket' => $bucket,
            'totalMinutes' => $totalMinutes,
            'totalRate' => $totalRate,
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ], $filename, request: $request, reportCode: 'customer-project', filters: ['from' => $from, 'to' => $to, 'scope' => $scope, 'foreign_customer' => $foreignCustomerId]);
    }
}
