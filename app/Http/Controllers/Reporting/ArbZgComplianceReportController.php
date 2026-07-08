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

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{Attendance, Organization, Team, TimeCorrectionRequest, User};
use App\Services\Compliance\{AttendanceComplianceChecker, AttendanceComplianceFinding};
use App\Support\{Sqid, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\{DB, Gate};
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
        /** @var Organization|null $org */
        $org = app()->bound('currentOrganization') && app('currentOrganization') instanceof Organization
            ? app('currentOrganization')
            : null;
        $checker = AttendanceComplianceChecker::forOrganization($org);

        /** @var Collection<int, User> $users */
        $users = User::query()->orderBy('name')->get(['id', 'name']);
        if ($users->isEmpty() || ! $checker->enabled()) {
            return ['rows' => []];
        }
        $userIds = $users->pluck('id')->map(static fn($v): int => (int) $v)->all();

        // Stempel-Spannen im Zeitraum laden (ohne abgesagte/offene). Ruhezeit
        // braucht den Vortag → Fenster um einen Tag nach vorn erweitern.
        /** @var Collection<int, Attendance> $attendances */
        $attendances = Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->copy()->subDay()->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [AttendanceStatus::Cancelled->value, AttendanceStatus::Open->value])
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->orderBy('started_at')
            ->get();

        // Genehmigte/angewandte Zeitkorrekturen im Zeitraum (nur Anzeige/Verweis).
        /** @var array<int, array<string, bool>> $correctedByUserDate */
        $correctedByUserDate = [];
        TimeCorrectionRequest::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', [TimeCorrectionStatus::Approved->value, TimeCorrectionStatus::Applied->value])
            ->whereBetween('scope_date', [$from->toDateString(), $to->toDateString()])
            ->get(['user_id', 'scope_date'])
            ->each(function (TimeCorrectionRequest $r) use (&$correctedByUserDate): void {
                $correctedByUserDate[(int) $r->user_id][$r->scope_date->toDateString()] = true;
            });

        $tz = Tz::current();

        /** @var array<int, array<string, list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>>> $spansByUserDate */
        $spansByUserDate = [];
        foreach ($attendances as $a) {
            if (! $a->started_at || ! $a->ended_at) {
                continue;
            }
            // Kalendertag in der Anzeige-Zeitzone (wie Attendance::saving()).
            $dateKey = $a->started_at->copy()->setTimezone($tz)->toDateString();
            $spansByUserDate[(int) $a->user_id][$dateKey][] = [
                'started_at' => CarbonImmutable::parse($a->started_at->toIso8601String()),
                'ended_at' => CarbonImmutable::parse($a->ended_at->toIso8601String()),
                'break_minutes' => $a->break_minutes_total,
            ];
        }

        $fromStr = $from->toDateString();
        $rows = [];
        foreach ($users as $u) {
            $uid = (int) $u->id;
            $byDate = $spansByUserDate[$uid] ?? [];
            if ($byDate === []) {
                continue;
            }
            $findings = $checker->checkUser($uid, $byDate);

            // Verstöße ausserhalb des angefragten Zeitraums (Tag −1 nur für die
            // Ruhezeit-Vorlauf) verwerfen.
            $findings = array_values(array_filter(
                $findings,
                static fn(AttendanceComplianceFinding $f): bool => $f->date >= $fromStr,
            ));
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
        /** @var Organization|null $org */
        $org = app()->bound('currentOrganization') && app('currentOrganization') instanceof Organization
            ? app('currentOrganization')
            : null;
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
