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

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Reporting\{ReportTargetMetric, ReportTargetScope};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Expense, MaterialUsage, Project, TimeEntry, Timesheet, User};
use App\Services\Reporting\{EconomicsReportBuilder, ReportFilters, ReportTargetEvaluator};
use App\Support\{CarbonFmt, Sqid};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Feature 014 (Nachkalkulation & Wirtschaftlichkeit): Deckungsbeitrags- und
 * Wirtschaftlichkeitssicht je Kunde/Projekt, inkl. Ranking (Top/Flop),
 * Nicht-Abrechenbar-/Nacharbeits-Proxy und Plan-vs-Ist. Org-weite Finanzdaten
 * → nur für Admins bzw. report.view-Berechtigte.
 */
class EconomicsReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
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

        [$from, $to] = $this->resolveRange($request);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        $filterFields = ['customer', 'project', 'include_excluded'];
        $filters = $this->standardFilters($request, $filterFields, $from, $to);
        // Legacy-Parameter project_id (alte Bookmarks) ins Standard-Set
        // übernehmen, damit Partial, Links und Audit denselben Stand sehen.
        $projectId = $filters->projectId ?? Sqid::decodeOrNumeric(Project::class, $request->query('project_id'));
        if ($projectId !== $filters->projectId) {
            $filters = new ReportFilters(
                from: $from,
                to: $to,
                customerId: $filters->customerId,
                projectId: $projectId,
                excludedCustomerIds: $filters->excludedCustomerIds,
                includeExcludedCustomers: $filters->includeExcludedCustomers,
            );
        }
        $customerId = $filters->customerId;

        // Feature 002: Ausblendung greift nur ohne explizite Kunden-/Projektwahl
        // (gleiche Übersteuerungsregel wie ReportFilters::customerExclusionActive()).
        $excludedCustomerIds = $customerId === null && $projectId === null
            ? $filters->excludedCustomerIds
            : [];

        $byProject = $this->builder->byProject($from, $to, $projectId !== null ? [$projectId] : null, $customerId, $excludedCustomerIds);
        $byCustomer = $this->builder->byCustomer($from, $to, $customerId, $projectId, $excludedCustomerIds);

        // MVP-332: LV-Dimension nur bei konkretem Projektfilter (die
        // Positionssicht ist projektgebunden; hasBoq=false → leerer Zustand).
        $boqDimension = $projectId !== null ? $this->builder->byBoqPosition($from, $to, $projectId) : null;

        $exportContext = $filters->toAuditArray();

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($byCustomer, $byProject, $from->toDateString(), $to->toDateString(), $exportContext, $request, $boqDimension);
        }

        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($byCustomer, $byProject, $label, $from->toDateString(), $to->toDateString(), $this->contributionSeries($byProject, $filters), $exportContext, $request, $boqDimension);
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

        $scatter = $this->marginVolumeScatter($byProject, $filters);

        return view('reports.economics', [
            'boqDimension' => $boqDimension,
            'marginTarget' => $marginTarget,
            'actualMargin' => $actualMargin,
            'customerMarginTargets' => $customerMarginTargets,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'projectId' => $projectId,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'contributionSeries' => $this->contributionSeries($byProject, $filters),
            'marginVolumeSeries' => $scatter['series'],
            'marginPercentiles' => $scatter['percentiles'],
            'monthlySeries' => $this->monthlySeries($filters, $excludedCustomerIds),
            'byCustomer' => $byCustomer,
            'byProject' => $byProject,
            'topProjects' => $topProjects,
            'flopProjects' => $flopProjects,
            'topCustomers' => $topCustomers,
            'flopCustomers' => $flopCustomers,
            'costRateMissing' => $costRateMissing,
            ...$this->standardFilterOptions($filterFields, $filters),
        ]);
    }

    /**
     * Deckungsbeitrag je Projekt (Top 15) — bar-h kann keine negativen Balken,
     * daher NUR positive Beiträge; negative stehen prominent in der
     * Flop-Tabelle. Drilldown = Selbstfilter der Seite auf das Projekt.
     *
     * @param  list<array<string, mixed>>  $byProject
     * @return list<array{x: string, y: float, url: string}>
     */
    private function contributionSeries(array $byProject, ReportFilters $filters): array {
        return array_values(collect($byProject)
            ->filter(static fn(array $row): bool => (float) $row['contribution'] > 0.0)
            ->sortByDesc('contribution')
            ->take(15)
            ->map(fn(array $row): array => [
                'x' => (string) $row['projectName'],
                'y' => round((float) $row['contribution'], 2),
                'url' => route('reports.economics', array_merge($filters->toQueryParams(), [
                    'project' => Sqid::encode(Project::class, (int) $row['projectId']),
                ])),
            ])
            ->all());
    }

    /**
     * Marge (%) je Projekt, aufsteigend nach Volumen (Stunden) — die
     * scatter-Komponente platziert Punkte index-basiert, daher dient die
     * Volumen-Sortierung als x-Dimension. Nur Projekte mit Erlös und
     * nicht-negativer Marge (Komponente zeichnet keine negativen y-Werte);
     * P50-Linie = Median-Marge, gekappt auf die 40 volumenstärksten Projekte.
     *
     * @param  list<array<string, mixed>>  $byProject
     * @return array{series: list<array{x: string, y: float, label: string, url: string}>, percentiles: array<string, float>}
     */
    private function marginVolumeScatter(array $byProject, ReportFilters $filters): array {
        $points = collect($byProject)
            ->filter(static fn(array $row): bool => (float) $row['revenue'] > 0.0 && (float) $row['margin'] >= 0.0)
            ->sortByDesc('totalMinutes')
            ->take(40)
            ->sortBy('totalMinutes')
            ->values();

        if ($points->isEmpty()) {
            return ['series' => [], 'percentiles' => []];
        }

        $margins = $points->pluck('margin')->map(static fn($v): float => (float) $v)->sort()->values();
        $mid = intdiv($margins->count(), 2);
        $median = $margins->count() % 2 === 1
            ? (float) $margins[$mid]
            : round(((float) $margins[$mid - 1] + (float) $margins[$mid]) / 2, 2);

        $series = $points->map(fn(array $row): array => [
            'x' => (string) $row['projectName'],
            'y' => (float) $row['margin'],
            'label' => sprintf('%s (%s h)', (string) $row['projectName'], NumberHelper::toGermanFormat(round(((int) $row['totalMinutes']) / 60, 1), 1)),
            'url' => route('reports.economics', array_merge($filters->toQueryParams(), [
                'project' => Sqid::encode(Project::class, (int) $row['projectId']),
            ])),
        ])->all();

        return ['series' => array_values($series), 'percentiles' => ['P50' => $median]];
    }

    /**
     * Erlös/Kosten aus Zeiten je Monat (Feature 002) — leere Serie statt
     * Null-Linie, wenn der Zeitraum keine bewerteten Zeiten trägt.
     *
     * @param  list<int>  $excludedCustomerIds
     * @return list<array{x: string, y: float, y2: float}>
     */
    private function monthlySeries(ReportFilters $filters, array $excludedCustomerIds = []): array {
        $months = $this->builder->timeByMonth($filters->from, $filters->to, $filters->customerId, $filters->projectId, $excludedCustomerIds);

        $hasData = collect($months)->contains(
            static fn(array $month): bool => $month['revenue'] > 0.0 || $month['cost'] > 0.0,
        );
        if (! $hasData) {
            return []; // Leerzustand statt Null-Linie (§Diagramm-UX).
        }

        return array_map(static fn(array $month): array => [
            'x' => $month['monthLabel'],
            'y' => $month['revenue'],
            'y2' => $month['cost'],
        ], $months);
    }

    /**
     * @param  list<array<string, mixed>>  $byCustomer
     * @param  list<array<string, mixed>>  $byProject
     * @param  array<string, mixed>        $filters
     * @param  array{hasBoq: bool, positions: list<array<string, mixed>>, unassigned: array<string, int|float>}|null  $byBoq
     */
    private function exportCsv(array $byCustomer, array $byProject, string $from, string $to, array $filters, Request $request, ?array $byBoq = null): Response {
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

        // MVP-332: LV-Dimension als eigene Sektion (nur mit Projektfilter + LV).
        if ($byBoq !== null && $byBoq['hasBoq']) {
            $num = static fn($v): string => $v === null ? '' : NumberHelper::toUSFormat((float) $v, 2);
            $out[] = [''];
            $out[] = [
                'LVPosition', 'Nachtrag', 'Kurztext', 'MengeAufmass', 'Einheit',
                'ErloesAufmassEUR', 'ZeitMin', 'KostenZeitEUR', 'KostenMaterialEUR', 'KostenEUR', 'DeckungsbeitragEUR',
            ];
            foreach ($byBoq['positions'] as $p) {
                $out[] = [
                    (string) $p['referenceNo'],
                    $p['isAddendum'] ? 'ja' : 'nein',
                    (string) ($p['shortText'] ?? ''),
                    NumberHelper::toUSFormat((float) $p['measuredQuantity'], 4),
                    (string) ($p['unit'] ?? ''),
                    $num($p['revenue']),
                    (string) $p['timeMinutes'],
                    $num($p['costTime']),
                    $num($p['costMaterial']),
                    $num($p['cost']),
                    $num($p['contribution']),
                ];
            }
            $u = $byBoq['unassigned'];
            $out[] = [
                '(ohne Zuordnung)', '', '', '', '',
                '', (string) $u['timeMinutes'], $num($u['costTime']), $num($u['costMaterial']), $num($u['cost']), '',
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'economics', $filters, $request);
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<string>
     */
    private function csvRow(string $level, string $name, string $customer, array $r): array {
        $num = static fn($v): string => $v === null ? '' : NumberHelper::toUSFormat((float) $v, 2);

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
     * @param  list<array{x: string, y: float, url: string}>  $contributionSeries
     * @param  array<string, mixed>        $filters
     * @param  array{hasBoq: bool, positions: list<array<string, mixed>>, unassigned: array<string, int|float>}|null  $byBoq
     */
    private function exportPdf(array $byCustomer, array $byProject, string $label, string $from, string $to, array $contributionSeries, array $filters, Request $request, ?array $byBoq = null): SymfonyResponse {
        $filename = sprintf('wirtschaftlichkeit_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.economics', [
            'byCustomer' => $byCustomer,
            'byProject' => $byProject,
            'byBoq' => $byBoq,
            'label' => $label,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Top-Deckungsbeiträge je Projekt (nur positive)'),
                'unit' => '€',
                'xLabel' => __('Projekt'),
                'yLabel' => __('Deckungsbeitrag (€)'),
                'series' => $contributionSeries,
            ],
        ], $filename, 'landscape', $request, 'economics', $filters);
    }

    /**
     * Beleg-Drilldown (Rang 59c + MVP-332 Belegtiefe): Quellposten einer
     * Report-Zelle — Zugriff nur über signierten, kurzlebigen Link
     * (temporarySignedRoute) PLUS Report-Recht (Whitebox-Leitplanke
     * Export-Authz; DSGVO: der Drilldown zeigt nur, was der Report ohnehin
     * aggregiert, unter demselben Recht); org-Scope über die Global Scopes.
     * Summen-Konsistenz: Fußzeile == Zellenwert; `expected` trägt den
     * Zellenwert und meldet Abweichungen sichtbar. Seitenwechsel bleibt
     * signiert, weil `page` von der Signaturprüfung ausgenommen ist.
     */
    public function drilldown(Request $request): View {
        abort_unless($request->hasValidSignatureWhileIgnoring(['page']), 403);

        $authUser = Auth::user();
        $allowed = $authUser instanceof User
            && ($authUser->isAdmin() || $authUser->can(Permission::ReportView->value));
        abort_unless($allowed, 403);

        $kind = (string) $request->query('kind');
        abort_unless(in_array($kind, ['rework', 'goodwill', 'time', 'material', 'expense', 'travel'], true), 404);

        $project = Project::query()->findOrFail(Sqid::decodeOrNumeric(Project::class, $request->query('project')) ?? 0);
        $from = (string) $request->query('from');
        $to = (string) $request->query('to');
        $expected = $request->query('expected') !== null ? (float) $request->query('expected') : null;

        $data = match ($kind) {
            'time' => $this->timeDrilldown($project, $from, $to),
            'material' => $this->materialDrilldown($project, $from, $to),
            'expense' => $this->expenseDrilldown($project, $from, $to),
            'travel' => $this->travelDrilldown($project, $from, $to),
            default => $this->reasonDrilldown($kind, $project, $from, $to),
        };

        return view('reports.economics-drilldown', array_merge([
            'kind' => $kind,
            'project' => $project,
            'from' => $from,
            'to' => $to,
            'expected' => $expected,
            'consistent' => $expected === null || abs($expected - (float) ($data['totalCost'] ?? 0.0)) < 0.01,
        ], $data));
    }

    /**
     * Bestands-Drilldown Nacharbeit/Kulanz (Rang 59c) — unverändert ungeteilt,
     * die Summe der Fußzeile entspricht dem Zellenwert (Minuten).
     *
     * @return array<string, mixed>
     */
    private function reasonDrilldown(string $kind, Project $project, string $from, string $to): array {
        $column = $kind === 'rework' ? 'rework_reason_classification_id' : 'goodwill_reason_classification_id';
        $entries = TimeEntry::query()
            ->where('project_id', $project->id)
            ->whereBetween('date', [$from, $to])
            ->whereNotNull($column)
            ->with(['user:id,name', $kind === 'rework' ? 'reworkReason:id,label' : 'goodwillReason:id,label'])
            ->orderBy('date')
            ->get();

        return [
            'entries' => $entries,
            'totalMinutes' => (int) $entries->sum('minutes'),
        ];
    }

    /**
     * Belegtiefe Zeit: alle Zeiteinträge des Projekts im Zeitraum (Kosten =
     * interner Satz; Erlös nur für abrechenbare Einträge) — identische
     * Abgrenzung wie {@see EconomicsReportBuilder::byProject()}.
     *
     * @return array<string, mixed>
     */
    private function timeDrilldown(Project $project, string $from, string $to): array {
        $base = TimeEntry::query()
            ->where('project_id', $project->id)
            ->whereBetween('date', [$from, $to]);

        return [
            'totalMinutes' => (int) $base->clone()->sum('minutes'),
            'totalRevenue' => round((float) $base->clone()->where('billable', true)->sum('rate'), 2),
            'totalCost' => round((float) $base->clone()->sum('internal_rate'), 2),
            'rows' => $base->clone()
                ->with('user:id,name')
                ->orderBy('date')->orderBy('id')
                ->paginate(50)->withQueryString(),
        ];
    }

    /**
     * Belegtiefe Material: Materialpositionen über die Projekt-Timesheets im
     * Zeitraum (Kosten = Netto-Direktaufwand, Erlös = abgerechnete Positionen).
     *
     * @return array<string, mixed>
     */
    private function materialDrilldown(Project $project, string $from, string $to): array {
        $timesheetIds = Timesheet::query()
            ->where('project_id', $project->id)
            ->whereBetween('work_date', [$from, $to])
            ->pluck('id')
            ->all();

        $base = MaterialUsage::query()->whereIn('timesheet_id', $timesheetIds === [] ? [0] : $timesheetIds);

        return [
            'totalRevenue' => round((float) $base->clone()->where('billed', true)->sum('line_total_net'), 2),
            'totalCost' => round((float) $base->clone()->sum('line_total_net'), 2),
            'rows' => $base->clone()
                ->with(['timesheet:id,work_date', 'material:id,name'])
                ->orderBy('timesheet_id')->orderBy('id')
                ->paginate(50)->withQueryString(),
        ];
    }

    /**
     * Belegtiefe Fahrt (Vollaudit 2026-07, M7): erstattungsfähige Fahrten des
     * Projekts im Zeitraum — identische Abgrenzung wie
     * {@see EconomicsReportBuilder::travelAggregate()} (Kostenseite).
     *
     * @return array<string, mixed>
     */
    private function travelDrilldown(Project $project, string $from, string $to): array {
        $base = \App\Models\TravelLog::query()
            ->where('project_id', $project->id)
            ->whereBetween('date', [$from, $to])
            ->where('reimbursable', true);

        return [
            'totalCost' => round((float) $base->clone()->sum('reimbursement_total'), 2),
            'rows' => $base->clone()
                ->with('user:id,name')
                ->orderBy('date')->orderBy('id')
                ->paginate(50)->withQueryString(),
        ];
    }

    /**
     * Belegtiefe Spesen/Belege: freigegebene/erstattete/fakturierte Spesen des
     * Projekts im Zeitraum inkl. Kategorie und Verknüpfung zum Beleg.
     *
     * @return array<string, mixed>
     */
    private function expenseDrilldown(Project $project, string $from, string $to): array {
        $settled = [
            ExpenseStatus::Approved->value,
            ExpenseStatus::Reimbursed->value,
            ExpenseStatus::Invoiced->value,
        ];

        $base = Expense::query()
            ->where('project_id', $project->id)
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', $settled);

        return [
            'totalRevenue' => round((float) $base->clone()->where('billable', true)->sum('amount_net'), 2),
            'totalCost' => round((float) $base->clone()->sum('amount_net'), 2),
            'rows' => $base->clone()
                ->with('category:id,label')
                ->orderBy('date')->orderBy('id')
                ->paginate(50)->withQueryString(),
        ];
    }
}
