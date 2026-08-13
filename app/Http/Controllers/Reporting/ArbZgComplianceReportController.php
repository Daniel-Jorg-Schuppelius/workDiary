<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArbZgComplianceReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Compliance\ComplianceFindingStatus;
use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{ComplianceFinding, Organization, Team, TimeCorrectionRequest, User};
use App\Services\Compliance\{AttendanceComplianceChecker, AttendancePlausibilityScanService, ComplianceFindingRecorder, ComplianceFindingService, ComplianceScanService};
use App\Services\Reporting\ReportFilters;
use App\Support\{ChartBucket, Sqid};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * ArbZG-Compliance-Auswertung (Feature 006) auf der tatsächlich erfassten
 * Arbeitszeit (Ist), nicht auf der Dienstplan-Vorausschau. Prüft je
 * Mitarbeiter/Tag gegen die ArbZG-Schwellen ({@see AttendanceComplianceChecker});
 * Verstöße werden on-the-fly berechnet (keine Persistenz).
 */
class ArbZgComplianceReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize(Permission::ComplianceViewAny->value);

        [$from, $to] = $this->resolveRange($request);
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $filters = $this->standardFilters($request, ['user', 'team'], $from, $to);

        $kindFilter = $request->string('kind')->toString();
        $kindFilter = in_array($kindFilter, $this->kinds(), true) ? $kindFilter : '';

        $data = $this->build($from, $to);
        $rows = $this->filterRowsByUserTeam($data['rows'], $filters);
        if ($kindFilter !== '') {
            // Auf die gewählte Verstoßart eingrenzen (Zeilen, Befunde und Counts).
            $filtered = [];
            foreach ($rows as $r) {
                $findings = array_values(array_filter(
                    $r['findings'],
                    static fn(array $f): bool => $f['kind'] === $kindFilter,
                ));
                if ($findings === []) {
                    continue;
                }
                $counts = array_fill_keys($this->kinds(), 0);
                $counts[$kindFilter] = count($findings);
                $filtered[] = ['user' => $r['user'], 'findings' => $findings, 'counts' => $counts];
            }
            $rows = $filtered;
        }
        $summary = $this->summarize($rows);
        $exportFilters = array_merge(['kind' => $kindFilter], $filters->toAuditArray());

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($rows, $fromStr, $toStr, $exportFilters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $summary, $fromStr, $toStr, $exportFilters, $request);
        }

        [$heatmapRows, $monthLabels] = $this->userMonthHeatmap($rows, $from, $to);

        return view('reports.arbzg-compliance', [
            'rows' => $rows,
            'summary' => $summary,
            'from' => $fromStr,
            'to' => $toStr,
            'kinds' => $this->kinds(),
            'kindFilter' => $kindFilter,
            'thresholds' => $this->thresholdLabels(),
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team'],
            'monthlyKindSeries' => $this->monthlyKindSeries($rows, $from, $to),
            'kindBands' => $this->kindBands(),
            'heatmapRows' => $heatmapRows,
            'monthLabels' => $monthLabels,
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($from, $to)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($from, $to)),
            ...$this->standardFilterOptions(['user', 'team'], $filters),
        ]);
    }

    /**
     * Zeilen auf gewählten Mitarbeiter bzw. Team-Mitglieder eingrenzen
     * (Anzeige-Filter — die Ermittlung bleibt org-weit im ScanService).
     *
     * @param  array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>  $rows
     * @return array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>
     */
    private function filterRowsByUserTeam(array $rows, ReportFilters $filters): array {
        $teamUserIds = $filters->teamUserIds();
        if ($filters->userId === null && $teamUserIds === []) {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $r) use ($filters, $teamUserIds): bool {
            $uid = (int) $r['user']->id;
            if ($filters->userId !== null && $uid !== $filters->userId) {
                return false;
            }

            return $teamUserIds === [] || in_array($uid, $teamUserIds, true);
        }));
    }

    /**
     * Befunde je Monat, gestapelt nach Verstoßart (Screen + PDF).
     *
     * @param  array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>  $rows
     * @return list<array<string, string|int>>
     */
    private function monthlyKindSeries(array $rows, CarbonImmutable $from, CarbonImmutable $to): array {
        $granularity = $this->bucketGranularity($from, $to);
        $bucketList = $this->buildBucketsInRange($from, $to);
        /** @var array<string, array<string, int>> $byKey */
        $byKey = [];
        foreach ($bucketList as $bucket) {
            $byKey[$bucket['key']] = array_fill_keys($this->kinds(), 0);
        }
        $total = 0;
        foreach ($rows as $r) {
            foreach ($r['findings'] as $f) {
                $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $f['date']))[0];
                if (isset($byKey[$key][(string) $f['kind']])) {
                    $byKey[$key][(string) $f['kind']]++;
                    $total++;
                }
            }
        }
        if ($total === 0) {
            return []; // Leerzustand statt Null-Serie (§Diagramm-UX).
        }

        $series = [];
        foreach ($bucketList as $bucket) {
            $series[] = ['x' => $bucket['shortLabel']] + $byKey[$bucket['key']];
        }

        return $series;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function kindBands(): array {
        return array_map(fn(string $kind): array => [
            'key' => $kind,
            'label' => (string) __('compliance.report.kind.' . $kind),
        ], $this->kinds());
    }

    /**
     * Heatmap Mitarbeiter × Monat (Befundanzahl).
     *
     * @param  array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>  $rows
     * @return array{0: list<array{label: string, cells: list<array{value: int}>}>, 1: list<string>}
     */
    private function userMonthHeatmap(array $rows, CarbonImmutable $from, CarbonImmutable $to): array {
        $months = $this->buildMonthsInRange($from, $to);
        $monthKeys = array_column($months, 'key');
        $heatmapRows = [];
        foreach ($rows as $r) {
            $cells = array_fill_keys($monthKeys, 0);
            foreach ($r['findings'] as $f) {
                $monthKey = substr((string) $f['date'], 0, 7);
                if (array_key_exists($monthKey, $cells)) {
                    $cells[$monthKey]++;
                }
            }
            $heatmapRows[] = [
                'label' => (string) $r['user']->name,
                'cells' => array_map(static fn(int $count): array => ['value' => $count], array_values($cells)),
            ];
        }

        return [$heatmapRows, array_column($months, 'shortLabel')];
    }

    /**
     * Org-Dashboard (Rang 39): KPI-Kacheln, Verstoß-Zeitreihe je Regel und
     * Team-Aggregation (bewusst teambezogen — kein Personen-Scoring, Drilldown
     * führt in den Einzelreport). „Offen" = Befund ohne genehmigte Zeitkorrektur
     * am Tag; Berechnung wie im Einzelreport (build()).
     */
    public function dashboard(Request $request): View {
        Gate::authorize(Permission::ComplianceViewAny->value);

        [$from, $to] = $this->resolveRange($request);

        $filters = $this->standardFilters($request, ['team'], $from, $to);

        $rows = $this->filterRowsByUserTeam($this->build($from, $to)['rows'], $filters);
        $summary = $this->summarize($rows);

        // Zeitreihe: Befunde je Regel × Monat (aus den Befund-Daten, keine Doppellogik).
        $months = [];
        $cursor = $from->startOfMonth();
        while ($cursor->lessThanOrEqualTo($to)) {
            $months[$cursor->format('Y-m')] = array_fill_keys($this->kinds(), 0);
            $cursor = $cursor->addMonth();
        }
        $openCount = 0;
        $correctedCount = 0;
        foreach ($rows as $row) {
            foreach ($row['findings'] as $finding) {
                $month = substr((string) $finding['date'], 0, 7);
                if (isset($months[$month][$finding['kind']])) {
                    $months[$month][$finding['kind']]++;
                }
                if (($finding['corrected'] ?? false) === true) {
                    $correctedCount++;
                } else {
                    $openCount++;
                }
            }
        }

        // Team-Aggregation: Befunde je Team (User können mehreren Teams angehören).
        $teamNames = Team::query()->whereNull('archived_at')->pluck('name', 'id');
        $teamIdsByUser = DB::table('team_user')
            ->whereIn('user_id', array_map(static fn(array $r): int => (int) $r['user']->id, $rows))
            ->get(['user_id', 'team_id'])
            ->groupBy('user_id');
        $byTeam = [];
        foreach ($rows as $row) {
            $findingCount = count($row['findings']);
            if ($findingCount === 0) {
                continue;
            }
            $teams = $teamIdsByUser->get($row['user']->id, collect());
            if ($teams->isEmpty()) {
                $byTeam[__('Ohne Team')] = ($byTeam[__('Ohne Team')] ?? 0) + $findingCount;

                continue;
            }
            foreach ($teams as $pivot) {
                $name = (string) ($teamNames[(int) $pivot->team_id] ?? __('Ohne Team'));
                $byTeam[$name] = ($byTeam[$name] ?? 0) + $findingCount;
            }
        }
        arsort($byTeam);

        return view('reports.compliance-dashboard', [
            'summary' => $summary,
            'months' => $months,
            'byTeam' => $byTeam,
            'openCount' => $openCount,
            'correctedCount' => $correctedCount,
            'kinds' => $this->kinds(),
            'thresholds' => $this->thresholdLabels(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'standardFilters' => $filters,
            'filterFields' => ['team'],
            'openMonthlySeries' => $this->openMonthlySeries($rows, $from, $to),
            'monthlyKindSeries' => $this->monthlyKindSeries($rows, $from, $to),
            'kindBands' => $this->kindBands(),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($from, $to)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($from, $to)),
            ...$this->standardFilterOptions(['team'], $filters),
        ]);
    }

    /**
     * Offene Befunde (ohne genehmigte Korrektur) je Monat — Verlaufskurve.
     * Die On-the-fly-Ermittlung kennt keinen historischen Status-Stand,
     * daher zählt die Reihe Befund-Monate, nicht Status-Schnappschüsse.
     *
     * @param  array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>  $rows
     * @return list<array{x: string, y: int}>
     */
    private function openMonthlySeries(array $rows, CarbonImmutable $from, CarbonImmutable $to): array {
        $granularity = $this->bucketGranularity($from, $to);
        $bucketList = $this->buildBucketsInRange($from, $to);
        /** @var array<string, int> $byKey */
        $byKey = [];
        foreach ($bucketList as $bucket) {
            $byKey[$bucket['key']] = 0;
        }
        $total = 0;
        foreach ($rows as $r) {
            foreach ($r['findings'] as $f) {
                $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $f['date']))[0];
                if (($f['corrected'] ?? false) !== true && array_key_exists($key, $byKey)) {
                    $byKey[$key]++;
                    $total++;
                }
            }
        }
        if ($total === 0) {
            return []; // Leerzustand statt Null-Linie (§Diagramm-UX).
        }

        $series = [];
        foreach ($bucketList as $bucket) {
            $series[] = ['x' => $bucket['shortLabel'], 'y' => $byKey[$bucket['key']]];
        }

        return $series;
    }

    /**
     * @return array{rows: array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>}
     */
    private function build(CarbonImmutable $from, CarbonImmutable $to): array {
        $org = $this->currentOrganization();
        if (! $org instanceof Organization) {
            return ['rows' => []];
        }

        // Ermittlung im ComplianceScanService, damit Report (Anzeige) und Scan-Command
        // (Persistenz) dieselbe Logik teilen; Anzeige-Aufbereitung (Sqid, Korrektur-Badge) bleibt hier.
        $findingsByUser = app(ComplianceScanService::class)->findingsForRange($org, $from, $to);
        if ($findingsByUser === []) {
            return ['rows' => []];
        }

        // Mandantengrenze: User hat KEINEN globalen OrganizationScope — ohne expliziten
        // Org-Filter erschienen User aller Orgs als Zeilen (Tenant-Leak, Bauturbo A17).
        /** @var Collection<int, User> $users */
        $users = User::query()
            ->where('organization_id', $org->getKey())
            ->orderBy('name')
            ->get(['id', 'name']);

        // Genehmigte/angewandte Zeitkorrekturen im Zeitraum (nur Anzeige/Verweis).
        /** @var array<int, array<string, bool>> $correctedByUserDate */
        $correctedByUserDate = [];
        TimeCorrectionRequest::query()
            ->whereIn('user_id', array_keys($findingsByUser))
            ->whereIn('status', [TimeCorrectionStatus::Approved->value, TimeCorrectionStatus::Applied->value])
            ->whereBetween('scope_date', [$from->toDateString(), $to->toDateString()])
            ->get(['user_id', 'scope_date'])
            ->each(function (TimeCorrectionRequest $r) use (&$correctedByUserDate): void {
                $correctedByUserDate[(int) $r->user_id][$r->scope_date->toDateString()] = true;
            });

        $rows = [];
        foreach ($users as $u) {
            $uid = (int) $u->id;
            $findings = $findingsByUser[$uid] ?? [];
            if ($findings === []) {
                continue;
            }

            $counts = array_fill_keys($this->kinds(), 0);
            $out = [];
            foreach ($findings as $f) {
                $counts[$f->kind] = ($counts[$f->kind] ?? 0) + 1;
                $out[] = $f->toArray() + [
                    'user_sqid' => Sqid::encode(User::class, $uid),
                    'corrected' => $correctedByUserDate[$uid][$f->date] ?? false,
                ];
            }

            $rows[] = [
                'user' => $u,
                'findings' => $out,
                'counts' => $counts,
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * Verstoß-Historie (Feature 006, Welle D): persistierte {@see ComplianceFinding}
     * mit Status-Filter und Acknowledge-Workflow (revisionssichere Sicht samt Bearbeitungsstand).
     */
    public function history(Request $request): View {
        Gate::authorize(Permission::ComplianceViewAny->value);

        // Zeitraum nur als Filterkontext — die Historie bleibt bewusst
        // vollständig (revisionssichere Sicht ohne Zeitraum-Beschnitt).
        [$from, $to] = $this->resolveRange($request);
        $statuses = array_column(ComplianceFindingStatus::cases(), 'value');
        $filters = $this->standardFilters($request, ['user', 'team', 'status'], $from, $to, $statuses);
        $statusFilter = $filters->status ?? '';

        // MVP-519: ArbZG-Verstöße und Plausibilitäts-Befunde („Ungeklärte
        // Fälle") teilen sich die Historie; die Kategorie grenzt ein.
        $categories = [ComplianceFindingRecorder::CATEGORY, AttendancePlausibilityScanService::CATEGORY];
        $categoryFilter = $request->string('category')->toString();
        $categoryFilter = in_array($categoryFilter, $categories, true) ? $categoryFilter : '';

        $query = ComplianceFinding::query()
            ->with(['subject', 'acknowledgedByUser:id,name'])
            ->orderByDesc('scope_date')
            ->orderBy('rule_code')
            ->orderByDesc('id');
        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }
        if ($categoryFilter !== '') {
            $query->where('category', $categoryFilter);
        }
        if ($filters->userId !== null || $filters->teamUserIds() !== []) {
            // Betroffene sind User-Morphs; Fremd-Subjekte gleicher ID ausschließen.
            $query->where('subject_type', (new User)->getMorphClass());
            $filters->applyUserAndTeam($query, 'subject_id');
        }

        $ackSeries = $this->acknowledgeSeries(clone $query);
        $findings = $query->paginate(50)->withQueryString();

        /** @var \Illuminate\Support\Collection<string, int> $counts */
        $counts = ComplianceFinding::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('reports.compliance-history', [
            'findings' => $findings,
            'statuses' => $statuses,
            'statusFilter' => $statusFilter,
            'categories' => $categories,
            'categoryFilter' => $categoryFilter,
            'counts' => $counts,
            'thresholds' => $this->thresholdLabels(),
            'canManage' => Gate::allows(Permission::ComplianceViewAny->value),
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team', 'status'],
            'ackSeries' => $ackSeries,
            ...$this->standardFilterOptions(['user', 'team'], $filters),
        ]);
    }

    /**
     * Neue vs. quittierte Befunde je Monat (neu = scope_date,
     * quittiert = acknowledged_at); letzte 24 Monate mit Daten.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ComplianceFinding>  $query
     * @return list<array{x: string, y: int, y2: int}>
     */
    private function acknowledgeSeries(\Illuminate\Database\Eloquent\Builder $query): array {
        /** @var array<string, array{new: int, acked: int}> $byMonth */
        $byMonth = [];
        $query->reorder()
            ->withOnly([])
            ->get(['scope_date', 'acknowledged_at'])
            ->each(function (ComplianceFinding $finding) use (&$byMonth): void {
                $newKey = $finding->scope_date->format('Y-m');
                $byMonth[$newKey] ??= ['new' => 0, 'acked' => 0];
                $byMonth[$newKey]['new']++;
                if ($finding->acknowledged_at !== null) {
                    $ackKey = $finding->acknowledged_at->format('Y-m');
                    $byMonth[$ackKey] ??= ['new' => 0, 'acked' => 0];
                    $byMonth[$ackKey]['acked']++;
                }
            });
        if ($byMonth === []) {
            return []; // Leerzustand statt Null-Serie (§Diagramm-UX).
        }

        ksort($byMonth, SORT_STRING);
        $byMonth = array_slice($byMonth, -24, null, true);

        $series = [];
        foreach ($byMonth as $monthKey => $countsPerMonth) {
            $series[] = [
                'x' => Carbon::parse($monthKey . '-01')->translatedFormat('M Y'),
                'y' => $countsPerMonth['new'],
                'y2' => $countsPerMonth['acked'],
            ];
        }

        return $series;
    }

    /**
     * Verstoß quittieren (acknowledged) oder bewusst akzeptieren (accepted, Pflicht-Begründung).
     * Recht: compliance.viewAny; Org-Isolation via Sqid-Route-Bindung; Statuswechsel wird auditiert.
     */
    public function acknowledge(Request $request, ComplianceFinding $finding, ComplianceFindingService $service): RedirectResponse {
        Gate::authorize(Permission::ComplianceViewAny->value);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in([
                ComplianceFindingStatus::Acknowledged->value,
                ComplianceFindingStatus::Accepted->value,
            ])],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $service->acknowledge(
            $finding,
            ComplianceFindingStatus::from($data['status']),
            $user,
            $data['note'] ?? null,
        );

        return redirect()
            ->route('reports.compliance.history')
            ->with('success', __('compliance.history.acknowledged'));
    }

    private function currentOrganization(): ?Organization {
        return app()->bound('currentOrganization') && app('currentOrganization') instanceof Organization
            ? app('currentOrganization')
            : null;
    }

    /**
     * @param  array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>  $rows
     * @return array{total:int, by_kind: array<string,int>, employees:int}
     */
    private function summarize(array $rows): array {
        $byKind = array_fill_keys($this->kinds(), 0);
        $total = 0;
        foreach ($rows as $r) {
            foreach ($r['counts'] as $kind => $n) {
                $byKind[$kind] = ($byKind[$kind] ?? 0) + $n;
                $total += $n;
            }
        }

        return [
            'total' => $total,
            'by_kind' => $byKind,
            'employees' => count($rows),
        ];
    }

    /** @return list<string> */
    private function kinds(): array {
        return [
            AttendanceComplianceChecker::KIND_MAX_DAILY_HOURS,
            AttendanceComplianceChecker::KIND_REST_PERIOD,
            AttendanceComplianceChecker::KIND_BREAK_MISSING,
            AttendanceComplianceChecker::KIND_MAX_WEEKLY_HOURS,
        ];
    }

    /** @return array<string, string> Schwellwert-Beschriftungen (aus dem Bestand abgeleitet). */
    private function thresholdLabels(): array {
        $org = $this->currentOrganization();
        $s = $org ? $org->complianceSettings() : Organization::COMPLIANCE_DEFAULTS;

        return [
            AttendanceComplianceChecker::KIND_MAX_DAILY_HOURS => (string) $s['max_hours_day'] . ' h',
            AttendanceComplianceChecker::KIND_REST_PERIOD => (string) $s['min_rest_hours'] . ' h',
            AttendanceComplianceChecker::KIND_MAX_WEEKLY_HOURS => (string) $s['max_hours_week'] . ' h',
        ];
    }

    /**
     * @param  array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>  $rows
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $rows, string $from, string $to, array $exportFilters, Request $request): Response {
        $filename = sprintf('arbzg_compliance_%s_%s.csv', $from, $to);
        $out = [];
        $out[] = [
            (string) __('compliance.report.csv.employee'),
            (string) __('compliance.report.csv.date'),
            (string) __('compliance.report.csv.kind'),
            (string) __('compliance.report.csv.severity'),
            (string) __('compliance.report.csv.value'),
            (string) __('compliance.report.csv.threshold'),
            (string) __('compliance.report.csv.corrected'),
        ];
        foreach ($rows as $r) {
            foreach ($r['findings'] as $f) {
                $out[] = [
                    $r['user']->name,
                    (string) $f['date'],
                    (string) __('compliance.report.kind.' . $f['kind']),
                    (string) __('compliance.report.severity.' . $f['severity']),
                    $this->fmtMinutes((int) $f['value']),
                    $this->fmtMinutes((int) $f['threshold']),
                    $f['corrected'] ? (string) __('compliance.report.csv.yes') : '',
                ];
            }
        }

        return $this->csvWithMetadata($out, $filename, 'arbzg_compliance', $exportFilters, $request);
    }

    /**
     * @param  array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>  $rows
     * @param  array{total:int, by_kind: array<string,int>, employees:int}  $summary
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $rows, array $summary, string $from, string $to, array $exportFilters, Request $request): SymfonyResponse {
        $filename = sprintf('arbzg_compliance_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.arbzg-compliance', [
            'rows' => $rows,
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
            'kinds' => $this->kinds(),
            'chart' => [
                'type' => 'stacked-bar-h',
                'title' => __('Befunde je Monat nach Verstoßart'),
                'unit' => __('Befunde'),
                'xLabel' => __('Monat'),
                'series' => $this->monthlyKindSeries($rows, CarbonImmutable::parse($from), CarbonImmutable::parse($to)),
                'bands' => $this->kindBands(),
            ],
        ], $filename, request: $request, reportCode: 'arbzg_compliance', filters: $exportFilters);
    }

    private function fmtMinutes(int $minutes): string {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);

        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT);
    }
}
