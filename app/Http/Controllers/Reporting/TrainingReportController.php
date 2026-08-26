<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\Training\TrainingAssignment;
use App\Services\Training\TrainingComplianceService;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Schulungs-Auswertung (Feature 145): Erfüllungsgrad je Team, Rolle und
 * Kurs zum Stichtag plus fällige/überfällige Soll-Einträge — Grundlage des
 * Kompetenznachweises (ISO 45001 7.2). Export als CSV/XLSX/PDF über das
 * Report-Framework (Feature 002).
 *
 * @phpstan-import-type TrainingComplianceReport from TrainingComplianceService
 * @phpstan-import-type TrainingGroupRow from TrainingComplianceService
 */
class TrainingReportController extends Controller {
    use RendersReportPdf;

    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(
        private readonly TrainingComplianceService $compliance,
    ) {}

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize('viewAny', TrainingAssignment::class);

        $today = Carbon::today();
        // Stichtagsreport — der Zeitraum liefert nur den Filterkontext.
        [$rangeFrom, $rangeTo] = $this->resolveRange($request);
        $filters = $this->standardFilters($request, ['user', 'team'], $rangeFrom, $rangeTo);

        $query = TrainingAssignment::query();
        $filters->applyUserAndTeam($query, 'user_id');

        $report = $this->compliance->build($query, $today);
        $exportFilters = array_merge(['date' => $today->toDateString()], $filters->toAuditArray());

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($report, $exportFilters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($report, $exportFilters, $request);
        }

        return view('reports.training', [
            'report' => $report,
            'today' => $today,
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team'],
            'teamSeries' => $this->rateSeries($report['byTeam']),
            'courseSeries' => $this->rateSeries($report['byCourse']),
            ...$this->standardFilterOptions(['user', 'team'], $filters),
        ]);
    }

    /**
     * Erfüllungsgrad je Gruppe (Top 15) — Datenkontrakt für bar-h.
     *
     * @param  list<TrainingGroupRow>  $groups
     * @return list<array{x: string, y: float}>
     */
    private function rateSeries(array $groups): array {
        return array_map(
            static fn(array $group): array => ['x' => $group['label'], 'y' => $group['rate']],
            array_slice($groups, 0, 15),
        );
    }

    /**
     * @param  TrainingComplianceReport  $report
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $report, array $exportFilters, Request $request): Response {
        $rows = [
            ['gruppe', 'schluessel', 'bezeichnung', 'soll', 'erfuellt', 'faellig', 'ueberfaellig', 'erfuellungsgrad_prozent'],
            ['gesamt', '-', (string) __('training.report.total'), $report['totals']['assignments'], $report['totals']['fulfilled'], $report['totals']['due'], $report['totals']['overdue'], $report['totals']['rate']],
        ];
        foreach (['team' => $report['byTeam'], 'rolle' => $report['byRole'], 'kurs' => $report['byCourse']] as $label => $groups) {
            foreach ($groups as $group) {
                $rows[] = [$label, $group['key'], $group['label'], $group['total'], $group['fulfilled'], $group['due'], $group['overdue'], $group['rate']];
            }
        }
        $rows[] = [''];
        $rows[] = ['person', 'kurs', 'faellig_am', 'nachgewiesen_am', 'zustand', 'nachweis'];
        foreach ($report['rows'] as $row) {
            $rows[] = [$row['user'], $row['course'], $row['due_at'], $row['fulfilled_at'], $row['state'], $row['proof']];
        }

        return $this->csvWithMetadata(
            $rows,
            'schulungen_' . Carbon::today()->toDateString() . '.csv',
            'training_compliance',
            $exportFilters,
            $request,
        );
    }

    /**
     * @param  TrainingComplianceReport  $report
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $report, array $exportFilters, Request $request): SymfonyResponse {
        return $this->pdfDownload('reports.pdf.training', [
            'report' => $report,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('training.report.rate_by_team'),
                'unit' => '%',
                'xLabel' => __('training.report.team'),
                'yLabel' => '%',
                'series' => $this->rateSeries($report['byTeam']),
            ],
        ], 'schulungen_' . Carbon::today()->toDateString() . '.pdf', 'portrait', $request, 'training_compliance', $exportFilters);
    }
}
