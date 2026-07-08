<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EconomicsReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Reporting\{ReportTargetMetric, ReportTargetScope};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{Project, User};
use App\Services\Reporting\{EconomicsReportBuilder, ReportTargetEvaluator};
use App\Support\Sqid;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Feature 014 (Nachkalkulation & Wirtschaftlichkeit): Deckungsbeitrags- und
 * Wirtschaftlichkeitssicht je Kunde und je Projekt, inkl. Ranking (Top/Flop),
 * Nicht-Abrechenbar-/Nacharbeits-Proxy und Plan-vs-Ist (Zeit & Geld).
 *
 * Org-weite Finanzdaten → nur für Administratoren bzw. report.view-Berechtigte
 * (Geschäftsführung/Buchhaltung). Plan-Gating wie übrige Team-Auswertungen.
 */
class EconomicsReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function __construct(
        private readonly EconomicsReportBuilder $builder,
        private readonly ReportTargetEvaluator $targets,
    ) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $authUser = Auth::user();
        $allowed = $authUser instanceof User
            && ($authUser->isAdmin() || $authUser->can(Permission::ReportView->value));
        abort_unless($allowed, 403);

        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $rawProjectId = $request->query('project_id');
        $projectId = Sqid::decodeOrNumeric(Project::class, $rawProjectId);

        $byProject = $this->builder->byProject($from, $to, $projectId !== null ? [$projectId] : null);
        $byCustomer = $this->builder->byCustomer($from, $to);

        $exportContext = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'project_id' => $projectId,
        ];

        if ($request->query('export') === 'csv') {
            $this->auditExport($request, 'economics', 'csv', $exportContext);

            return $this->exportCsv($byCustomer, $byProject, $from->toDateString(), $to->toDateString(), $exportContext);
        }

        if ($request->query('export') === 'pdf') {
            $this->auditExport($request, 'economics', 'pdf', $exportContext);

            return $this->exportPdf($byCustomer, $byProject, $range['label'], $from->toDateString(), $to->toDateString());
        }

        $rankProjects = collect($byProject)->filter(static fn(array $r): bool => $r['revenue'] > 0.0 || $r['cost'] > 0.0);
        $topProjects = $rankProjects->sortByDesc('contribution')->take(5)->values();
        $flopProjects = $rankProjects->sortBy('contribution')->take(5)->values();
        $rankCustomers = collect($byCustomer);
        $topCustomers = $rankCustomers->sortByDesc('contribution')->take(5)->values();
        $flopCustomers = $rankCustomers->sortBy('contribution')->take(5)->values();

        $costRateMissing = collect($byProject)->contains('costRateMissing', true)
            || collect($byCustomer)->contains('costRateMissing', true);

        // Feature 002 (Zielwerte): org-weite Deckungsbeitrags-Marge gegen Ziel.
        // Ist-Marge = Summe DB / Summe Erlös über alle Kunden (gewichteter Wert,
        // konsistent zur Builder-Definition margin = contribution / revenue).
        $sumRevenue = (float) collect($byCustomer)->sum('revenue');
        $sumContribution = (float) collect($byCustomer)->sum('contribution');
        $actualMargin = $sumRevenue > 0.0 ? round(($sumContribution / $sumRevenue) * 100, 2) : null;
        $marginTargets = $this->targets->load(ReportTargetMetric::ContributionMargin, $to);
        $marginTarget = $this->targets->evaluate(
            ReportTargetMetric::ContributionMargin,
            $this->targets->resolve($marginTargets, ReportTargetScope::Org, null),
            $actualMargin,
        );

        // Per-Kunde Zielauflösung (spezifisches Kundenziel vor Org-Fallback).
        $customerMarginTargets = [];
        foreach ($byCustomer as $row) {
            $cid = (int) $row['customerId'];
            $eval = $this->targets->evaluate(
                ReportTargetMetric::ContributionMargin,
                $this->targets->resolve($marginTargets, ReportTargetScope::Customer, $cid),
                (float) $row['margin'],
            );
            if ($eval !== null) {
                $customerMarginTargets[$cid] = $eval;
            }
        }

        return view('reports.economics', [
            'marginTarget' => $marginTarget,
            'actualMargin' => $actualMargin,
            'customerMarginTargets' => $customerMarginTargets,
            'from' => $from,
            'to' => $to,
            'label' => $range['label'],
            'projectId' => $projectId,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'byCustomer' => $byCustomer,
            'byProject' => $byProject,
            'topProjects' => $topProjects,
            'flopProjects' => $flopProjects,
            'topCustomers' => $topCustomers,
            'flopCustomers' => $flopCustomers,
            'costRateMissing' => $costRateMissing,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $byCustomer
     * @param  list<array<string, mixed>>  $byProject
     * @param  array<string, mixed>        $filters
     */
    private function exportCsv(array $byCustomer, array $byProject, string $from, string $to, array $filters): Response {
        $filename = sprintf('wirtschaftlichkeit_%s_%s.csv', $from, $to);
        $out = [];
        $out[] = [
            'Ebene', 'Name', 'Kunde', 'AbrechenbarMin', 'NichtAbrechenbarMin', 'GesamtMin',
            'NichtAbrechenbarAnteilProzent', 'ErloesEUR', 'KostenEUR', 'DeckungsbeitragEUR', 'MargeProzent',
            'PlanMin', 'IstMin', 'PlanIstDeltaMin', 'PlanBudgetEUR', 'IstKostenEUR', 'PlanIstDeltaEUR',
        ];

        foreach ($byCustomer as $r) {
            $out[] = $this->csvRow('Kunde', (string) $r['customerName'], '', $r);
        }
        foreach ($byProject as $r) {
            $out[] = $this->csvRow('Projekt', (string) $r['projectName'], (string) $r['customerName'], $r);
        }

        return $this->csvWithMetadata($out, $filename, 'economics', $filters);
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<string>
     */
    private function csvRow(string $level, string $name, string $customer, array $r): array {
        $num = static fn($v): string => $v === null ? '' : number_format((float) $v, 2, '.', '');

        return [
            $level,
            $name,
            $customer,
            (string) $r['billableMinutes'],
            (string) $r['nonBillableMinutes'],
            (string) $r['totalMinutes'],
            $num($r['nonBillableShare']),
            $num($r['revenue']),
            $num($r['cost']),
            $num($r['contribution']),
            $num($r['margin']),
            $r['planMinutes'] === null ? '' : (string) $r['planMinutes'],
            (string) $r['actualMinutes'],
            $r['planMinutesDelta'] === null ? '' : (string) $r['planMinutesDelta'],
            $r['planBudget'] === null ? '' : $num($r['planBudget']),
            $num($r['actualCost']),
            $r['planBudgetDelta'] === null ? '' : $num($r['planBudgetDelta']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $byCustomer
     * @param  list<array<string, mixed>>  $byProject
     */
    private function exportPdf(array $byCustomer, array $byProject, string $label, string $from, string $to): SymfonyResponse {
        $filename = sprintf('wirtschaftlichkeit_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.economics', [
            'byCustomer' => $byCustomer,
            'byProject' => $byProject,
            'label' => $label,
        ], $filename, 'landscape');
    }
    /**
     * Beleg-Drilldown (Rang 59c): Zeiteinträge einer Report-Zelle — Zugriff
     * nur über signierten, kurzlebigen Link (temporarySignedRoute) PLUS
     * Report-Recht (Whitebox-Leitplanke Export-Authz); org-Scope über die
     * Global Scopes. Summen-Konsistenz: Fußzeile == Zellenwert.
     */
    public function drilldown(Request $request): View {
        abort_unless($request->hasValidSignature(), 403);

        $authUser = Auth::user();
        $allowed = $authUser instanceof User
            && ($authUser->isAdmin() || $authUser->can(Permission::ReportView->value));
        abort_unless($allowed, 403);

        $kind = (string) $request->query('kind');
        abort_unless(in_array($kind, ['rework', 'goodwill'], true), 404);

        $project = Project::query()->findOrFail((int) $request->query('project'));
        $from = (string) $request->query('from');
        $to = (string) $request->query('to');

        $column = $kind === 'rework' ? 'rework_reason_classification_id' : 'goodwill_reason_classification_id';
        $entries = \App\Models\TimeEntry::query()
            ->where('project_id', $project->id)
            ->whereBetween('date', [$from, $to])
            ->whereNotNull($column)
            ->with(['user:id,name', $kind === 'rework' ? 'reworkReason:id,label' : 'goodwillReason:id,label'])
            ->orderBy('date')
            ->get();

        return view('reports.economics-drilldown', [
            'kind' => $kind,
            'project' => $project,
            'entries' => $entries,
            'from' => $from,
            'to' => $to,
            'totalMinutes' => (int) $entries->sum('minutes'),
        ]);
    }
}
