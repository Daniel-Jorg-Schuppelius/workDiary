<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Project;
use App\Models\TimeEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    use ResolvesGlobalDateRange;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        $isAdmin = Auth::user()?->isAdmin() ?? false;
        $scope = $request->string('scope', 'mine')->toString();
        if ($scope !== 'team' || ! $isAdmin) {
            $scope = 'mine';
        }

        $globalRange = $this->globalDateRange();
        $defaultFrom = $globalRange['from']->toDateString();
        $defaultTo = $globalRange['to']->toDateString();
        $from = (string) $request->input('from', $defaultFrom);
        $to = (string) $request->input('to', $defaultTo);

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        $query = TimeEntry::query()
            ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->select('project_id', 'minutes', 'rate', 'user_id');
        if ($scope === 'mine') {
            $query->where('user_id', $userId);
        }

        $entries = $query->get();

        // Aggregate: project_id => [minutes, rate]
        /** @var array<int, array{minutes: int, rate: float}> $byProject */
        $byProject = [];
        foreach ($entries as $e) {
            $pid = (int) $e->project_id;
            if (! isset($byProject[$pid])) {
                $byProject[$pid] = ['minutes' => 0, 'rate' => 0.0];
            }
            $byProject[$pid]['minutes'] += (int) $e->minutes;
            $byProject[$pid]['rate'] += (float) $e->rate;
        }

        // Lade Projekte + Customer.
        $projects = Project::with('customer')
            ->whereIn('id', array_keys($byProject))
            ->get()
            ->keyBy('id');

        // Gruppiere nach Customer (inkl. "Ohne Kunde"-Bucket).
        /** @var array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}> $bucket */
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

        // Sortiere Buckets nach Customer-Name; Projekte innerhalb nach Stunden DESC.
        uasort($bucket, function ($a, $b): int {
            $na = $a['customer'] instanceof Customer ? $a['customer']->name : '~~~';
            $nb = $b['customer'] instanceof Customer ? $b['customer']->name : '~~~';
            return strnatcasecmp($na, $nb);
        });
        foreach ($bucket as &$row) {
            uasort($row['projects'], fn($a, $b) => $b['minutes'] <=> $a['minutes']);
        }
        unset($row);

        $totalMinutes = array_sum(array_column($bucket, 'minutes'));
        $totalRate = array_sum(array_column($bucket, 'rate'));

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($bucket, $totalMinutes, $totalRate, $fromDate->toDateString(), $toDate->toDateString());
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($bucket, $totalMinutes, $totalRate, $fromDate->toDateString(), $toDate->toDateString(), $scope);
        }

        return view('reports.customer-project', [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'bucket' => $bucket,
            'totalMinutes' => $totalMinutes,
            'totalRate' => $totalRate,
        ]);
    }

    /**
     * @param array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}> $bucket
     */
    private function exportCsv(array $bucket, int $totalMinutes, float $totalRate, string $from, string $to): Response {
        $filename = sprintf('kunden-projekte_%s_%s.csv', $from, $to);
        $rows = [['Kunde', 'Projekt', 'Projektnummer', 'Minuten', 'Erloes']];
        foreach ($bucket as $row) {
            $customerName = $row['customer'] instanceof Customer ? $row['customer']->name : '(Ohne Kunde)';
            foreach ($row['projects'] as $entry) {
                $rows[] = [
                    $customerName,
                    (string) $entry['project']->name,
                    (string) ($entry['project']->number ?? ''),
                    (int) $entry['minutes'],
                    number_format((float) $entry['rate'], 2, '.', ''),
                ];
            }
        }
        $rows[] = ['Gesamt', '', '', $totalMinutes, number_format($totalRate, 2, '.', '')];

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                if (str_contains($s, ';') || str_contains($s, '"') || str_contains($s, "\n")) {
                    $s = '"' . str_replace('"', '""', $s) . '"';
                }
                return $s;
            }, $row)) . "\r\n";
        }

        return response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param array<int|string, array{customer: ?Customer, projects: array<int, array{project: Project, minutes: int, rate: float}>, minutes: int, rate: float}> $bucket
     */
    private function exportPdf(array $bucket, int $totalMinutes, float $totalRate, string $from, string $to, string $scope): SymfonyResponse {
        $filename = sprintf('kunden-projekte_%s_%s.pdf', $from, $to);
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.customer-project', [
            'bucket' => $bucket,
            'totalMinutes' => $totalMinutes,
            'totalRate' => $totalRate,
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ])->setPaper('a4');

        return $pdf->download($filename);
    }
}
