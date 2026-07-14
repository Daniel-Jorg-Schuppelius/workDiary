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
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{ComplianceFinding, Organization, Team, TimeCorrectionRequest, User};
use App\Services\Compliance\{AttendanceComplianceChecker, ComplianceFindingService, ComplianceScanService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * ArbZG-Compliance-Auswertung (Feature 006) auf der TATSÄCHLICH erfassten
 * Arbeitszeit (Attendance/Ist), nicht auf der Dienstplan-Vorausschau.
 *
 * Prüft je Mitarbeiter/Tag gegen die ArbZG-Schwellen aus dem Bestand
 * ({@see AttendanceComplianceChecker} → Organization::complianceSettings() +
 * BreakRuleEvaluator) und listet Verstöße (Art, Wert, Schwelle, Schweregrad).
 * Verstöße werden on-the-fly berechnet (keine Persistenz; die zugrunde
 * liegenden Attendance-Datensätze sind über die Audit-Hash-Kette ohnehin
 * revisionssicher).
 */
class ArbZgComplianceReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize(Permission::ComplianceViewAny->value);

        $range = $this->globalDateRange();
        $from = $range['from'];
        $to = $range['to'];
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $kindFilter = $request->string('kind')->toString();
        $kindFilter = in_array($kindFilter, $this->kinds(), true) ? $kindFilter : '';

        $data = $this->build($from, $to);
        $rows = $data['rows'];
        if ($kindFilter !== '') {
            // Auf die gewählte Verstoßart eingrenzen: sowohl Zeilen als auch die
            // darin gelisteten Befunde (und Counts) werden gefiltert.
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

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($rows, $fromStr, $toStr, $kindFilter);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $summary, $fromStr, $toStr);
        }

        return view('reports.arbzg-compliance', [
            'rows' => $rows,
            'summary' => $summary,
            'from' => $fromStr,
            'to' => $toStr,
            'kinds' => $this->kinds(),
            'kindFilter' => $kindFilter,
            'thresholds' => $this->thresholdLabels(),
        ]);
    }

    /**
     * Org-Dashboard (Rang 39): KPI-Kacheln, Verstoß-Zeitreihe je Regel und
     * Team-Aggregation (bewusst teambezogen — kein Personen-Scoring in der
     * Übersicht, Drilldown führt in den Einzelreport). „Offen" = Befund ohne
     * genehmigte Zeitkorrektur am betroffenen Tag; Berechnung identisch zum
     * Einzelreport (gleiches build()).
     */
    public function dashboard(): View {
        Gate::authorize(Permission::ComplianceViewAny->value);

        $range = $this->globalDateRange();
        $from = $range['from'];
        $to = $range['to'];

        $rows = $this->build($from, $to)['rows'];
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
            ->whereIn('user_id', array_map(static fn (array $r): int => (int) $r['user']->id, $rows))
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
        ]);
    }

    /**
     * @return array{rows: array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>}
     */
    private function build(CarbonImmutable $from, CarbonImmutable $to): array {
        $org = $this->currentOrganization();
        if (! $org instanceof Organization) {
            return ['rows' => []];
        }

        // Verstöße weiterhin ON-THE-FLY berechnen (unverändertes Report-
        // Verhalten). Die reine Ermittlung liegt jetzt im ComplianceScanService,
        // damit Report (Anzeige) und Scan-Command (Persistenz) dieselbe Logik
        // teilen; die Anzeige-Aufbereitung (Sqid, Korrektur-Badge) bleibt hier.
        $findingsByUser = app(ComplianceScanService::class)->findingsForRange($org, $from, $to);
        if ($findingsByUser === []) {
            return ['rows' => []];
        }

        // Mandantengrenze: User hat KEINEN globalen OrganizationScope — ohne
        // expliziten Org-Filter erschienen User ALLER Organisationen als
        // Report-Zeilen (Tenant-Leak, Bauturbo A17).
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
     * Verstoß-Historie (Feature 006, Welle D): die persistierten
     * {@see ComplianceFinding} mit Status-Filter und Acknowledge-Workflow.
     * Ergänzt die on-the-fly-Ansicht um die revisionssichere Sicht samt
     * Bearbeitungsstand.
     */
    public function history(Request $request): View {
        Gate::authorize(Permission::ComplianceViewAny->value);

        $statuses = array_column(ComplianceFindingStatus::cases(), 'value');
        $statusFilter = $request->string('status')->toString();
        $statusFilter = in_array($statusFilter, $statuses, true) ? $statusFilter : '';

        $query = ComplianceFinding::query()
            ->with(['subject', 'acknowledgedByUser:id,name'])
            ->orderByDesc('scope_date')
            ->orderBy('rule_code')
            ->orderByDesc('id');
        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

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
            'counts' => $counts,
            'thresholds' => $this->thresholdLabels(),
            'canManage' => Gate::allows(Permission::ComplianceViewAny->value),
        ]);
    }

    /**
     * Einen Verstoß quittieren (status=acknowledged) oder bewusst akzeptieren
     * (status=accepted, Pflicht-Begründung). Recht = bestehendes
     * Compliance-Recht (compliance.viewAny); Org-Isolation über die
     * Sqid-Route-Bindung (OrganizationScope). Statuswechsel wird auditiert.
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
     */
    private function exportCsv(array $rows, string $from, string $to, string $kindFilter): Response {
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

        return $this->csvWithMetadata($out, $filename, 'arbzg_compliance', [
            'from' => $from,
            'to' => $to,
            'kind' => $kindFilter,
        ]);
    }

    /**
     * @param  array<int, array{user: User, findings: list<array<string, mixed>>, counts: array<string,int>}>  $rows
     * @param  array{total:int, by_kind: array<string,int>, employees:int}  $summary
     */
    private function exportPdf(array $rows, array $summary, string $from, string $to): SymfonyResponse {
        $filename = sprintf('arbzg_compliance_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.arbzg-compliance', [
            'rows' => $rows,
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
            'kinds' => $this->kinds(),
        ], $filename);
    }

    private function fmtMinutes(int $minutes): string {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);

        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT);
    }
}
