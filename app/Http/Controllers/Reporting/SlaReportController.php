<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Reporting\{ReportTargetMetric, ReportTargetScope};
use App\Enums\ServiceTicket\{ServiceTicketPriority, SlaViolationKind};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{ServiceTicket, SlaContractQuota, SlaViolation, User};
use App\Services\Reporting\{ReportFilters, ReportTargetEvaluator};
use App\Services\ServiceTicket\{SlaQuotaService, SlaViolationService};
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * SLA-Auswertung (Feature 010): Verletzungen im Zeitraum mit Einhaltungsquote,
 * Aufschlüsselung je Typ (Reaktion/Lösung), Priorität und Kunde sowie einer
 * Verletzungsliste mit Drill-down zum Ticket und „Ursachen"-Gruppierung.
 *
 * Ungated (kein Plan-Modul) — Service-Tickets sind in config/plans.php keinem
 * Modul zugeordnet; die Sichtbarkeit steuert allein die Permission sla.viewAny.
 */
class SlaReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(
        private readonly ReportTargetEvaluator $targets,
        private readonly SlaQuotaService $quotas,
    ) {}

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize('viewAny', SlaViolation::class);

        [$fromDate, $toDate] = $this->resolveRange($request);
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $filters = $this->standardFilters($request, ['customer', 'include_excluded'], $fromDate, $toDate);

        $metrics = $this->aggregate($fromDate, $toDate, $filters);

        // Feature 002 (Zielwerte): Einhaltungsquote gegen org-weites Ziel (in %).
        $actualRate = $metrics['compliance_rate'] !== null ? round($metrics['compliance_rate'] * 100, 2) : null;
        $complianceTarget = $this->targets->compare(
            ReportTargetMetric::SlaComplianceRate,
            $actualRate,
            ReportTargetScope::Org,
            null,
            $toDate,
        );
        $metrics['compliance_target'] = $complianceTarget;

        /** @var array<int, array{name:string, count:int}> $byCustomer */
        $byCustomer = $metrics['by_customer'];
        $violationCustomerSeries = $this->violationsByCustomerSeries($byCustomer);
        $exportFilters = $filters->toAuditArray();

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($metrics, $from, $to, $request, $exportFilters);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($metrics, $from, $to, $violationCustomerSeries, $request, $exportFilters);
        }

        [$complianceSeries, $complianceMedian] = $this->monthlyComplianceSeries($fromDate, $toDate, $filters);

        return view('reports.sla', array_merge($metrics, [
            'from' => $from,
            'to' => $to,
            'canManage' => Gate::allows('acknowledge', new SlaViolation),
            'quotas' => $this->quotaUsage($toDate),
            'standardFilters' => $filters,
            'filterFields' => ['customer', 'include_excluded'],
            'complianceSeries' => $complianceSeries,
            'complianceMedian' => $complianceMedian,
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($fromDate, $toDate)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($fromDate, $toDate)),
            'violationCustomerSeries' => $violationCustomerSeries,
            ...$this->standardFilterOptions(['customer', 'include_excluded'], $filters),
        ]));
    }

    /**
     * Inklusivzeit-Kontingente (Feature 010 → Rang 44): Verbrauch je aktivem
     * Vertrags-Kontingent für die Periode, in der das Berichtsende liegt
     * (org-gescopt über den globalen Scope im HTTP-Kontext).
     *
     * @return list<array{contract: string, period: string, period_key: string, included: int, consumed: int, remaining: int, over: int, percentage: int, threshold_reached: bool}>
     */
    private function quotaUsage(CarbonImmutable $reference): array {
        $out = [];
        foreach (SlaContractQuota::query()->with('slaContract')->get() as $quota) {
            $contract = $quota->slaContract;
            if ($contract === null || ! $contract->is_active) {
                continue;
            }
            $usage = $this->quotas->usage($contract, $quota, $reference);
            $out[] = [
                'contract' => trim($contract->code . ' — ' . $contract->label, ' —'),
                'period' => $quota->period_kind->value,
                'period_key' => $usage['period_key'],
                'included' => $usage['included_minutes'],
                'consumed' => $usage['consumed_minutes'],
                'remaining' => $usage['remaining_minutes'],
                'over' => $usage['over_minutes'],
                'percentage' => $usage['percentage'],
                'threshold_reached' => $usage['threshold_reached'],
            ];
        }

        return $out;
    }

    /** Verletzung quittieren (Sichtung dokumentieren, optional Ursache). */
    public function acknowledge(Request $request, SlaViolation $violation, SlaViolationService $service): RedirectResponse {
        Gate::authorize('acknowledge', $violation);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $data = $request->validate([
            'cause' => ['nullable', 'string', 'max:191'],
        ]);

        $service->acknowledge($violation, $user, $data['cause'] ?? null);

        return redirect()
            ->route('reports.sla')
            ->with('success', __('sla.report.acknowledged'));
    }

    /**
     * @return array{
     *   total_tickets:int,
     *   violation_count:int,
     *   met_count:int,
     *   compliance_rate: float|null,
     *   by_kind: array<string, int>,
     *   by_priority: array<string, int>,
     *   by_customer: array<int, array{name:string, count:int}>,
     *   by_cause: array<string, int>,
     *   violations: \Illuminate\Database\Eloquent\Collection<int, SlaViolation>
     * }
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, ReportFilters $filters): array {
        // Feature 002: Ausblendung greift nur ohne explizite Kundenwahl
        // (gleiche Übersteuerungsregel wie ReportFilters::customerExclusionActive()).
        $excluded = $filters->customerId === null && $filters->projectId === null
            ? $filters->excludedCustomerIds
            : [];

        // Tickets mit Lösungsfrist im Zeitraum (gemeldet im Zeitraum) bilden die
        // Bezugsmenge für die Einhaltungsquote.
        $relevant = ServiceTicket::query()
            ->whereNotNull('resolution_due_at')
            ->whereBetween('reported_at', [$from, $to])
            ->when($filters->customerId !== null, fn($q) => $q->where('customer_id', $filters->customerId))
            // NOT IN würde NULL-Kunden mit verwerfen — kundenlose Tickets bleiben sichtbar.
            ->when($excluded !== [], fn($q) => $q->where(
                fn($w) => $w->whereNull('customer_id')->orWhereNotIn('customer_id', $excluded),
            ))
            ->count();

        /** @var Collection<int, SlaViolation> $violations */
        $violations = SlaViolation::query()
            ->with(['serviceTicket:id,ticket_no,title,customer_id,status', 'serviceTicket.customer:id,name'])
            ->whereBetween('breached_at', [$from, $to])
            ->when($filters->customerId !== null, fn($q) => $q->whereHas('serviceTicket', fn($t) => $t->where('customer_id', $filters->customerId)))
            // Feature 002: Verletzungen an Tickets ausgeblendeter Kunden entfallen
            // (whereDoesntHave hält Verletzungen ohne Ticket/Kunde sichtbar).
            ->when($excluded !== [], fn($q) => $q->whereDoesntHave('serviceTicket', fn($t) => $t->whereIn('customer_id', $excluded)))
            ->orderByDesc('breached_at')
            ->get();

        $byKind = array_fill_keys(SlaViolationKind::values(), 0);
        $byPriority = array_fill_keys(array_column(ServiceTicketPriority::cases(), 'value'), 0);
        /** @var array<int, array{name:string, count:int}> $byCustomer */
        $byCustomer = [];
        /** @var array<string, int> $byCause */
        $byCause = [];

        foreach ($violations as $v) {
            $byKind[$v->kind->value] = ($byKind[$v->kind->value] ?? 0) + 1;
            if ($v->priority !== null) {
                $byPriority[$v->priority] = ($byPriority[$v->priority] ?? 0) + 1;
            }
            $ticket = $v->serviceTicket;
            $customer = $ticket?->customer;
            $custId = $customer !== null ? (int) $customer->id : 0;
            if (! isset($byCustomer[$custId])) {
                $byCustomer[$custId] = [
                    'name' => $customer !== null ? $customer->name : (string) __('sla.report.no_customer'),
                    'count' => 0,
                ];
            }
            $byCustomer[$custId]['count']++;
            $cause = $v->cause !== null && trim($v->cause) !== '' ? trim($v->cause) : (string) __('sla.report.cause_unspecified');
            $byCause[$cause] = ($byCause[$cause] ?? 0) + 1;
        }

        uasort($byCustomer, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
        arsort($byCause);

        $violationCount = $violations->count();
        // Einhaltungsquote: Anteil der relevanten Tickets ohne Verletzung.
        $met = max(0, $relevant - $violationCount);

        return [
            'total_tickets' => $relevant,
            'violation_count' => $violationCount,
            'met_count' => $met,
            'compliance_rate' => $relevant > 0 ? fdiv($met, $relevant) : null,
            'by_kind' => $byKind,
            'by_priority' => $byPriority,
            'by_customer' => array_values($byCustomer),
            'by_cause' => $byCause,
            'violations' => $violations,
        ];
    }

    /**
     * SLA-Erfüllung (%) je Bucket (adaptiv zur Header-Granularität) des
     * Zeitraums + Median über die Buckets. Bezugsmenge je Bucket: gemeldete
     * Tickets mit Lösungsfrist; Verletzungen zählen im Bucket des Fristbruchs.
     * Buckets ohne Bezugsmenge entfallen; ohne jede Bezugsmenge → leere Serie
     * (§Diagramm-UX).
     *
     * @return array{0: list<array{x: string, y: float}>, 1: float|null}
     */
    private function monthlyComplianceSeries(CarbonImmutable $from, CarbonImmutable $to, ReportFilters $filters): array {
        $granularity = $this->bucketGranularity($from, $to);
        // Feature 002: gleiche Ausblendungs-/Übersteuerungsregel wie aggregate().
        $excluded = $filters->customerId === null && $filters->projectId === null
            ? $filters->excludedCustomerIds
            : [];

        /** @var array<string, int> $relevantByMonth */
        $relevantByMonth = [];
        $tickets = ServiceTicket::query()
            ->whereNotNull('resolution_due_at')
            ->whereBetween('reported_at', [$from, $to])
            ->when($filters->customerId !== null, fn($q) => $q->where('customer_id', $filters->customerId))
            ->when($excluded !== [], fn($q) => $q->where(
                fn($w) => $w->whereNull('customer_id')->orWhereNotIn('customer_id', $excluded),
            ))
            ->get(['reported_at']);
        foreach ($tickets as $ticket) {
            $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $ticket->reported_at))[0];
            $relevantByMonth[$key] = ($relevantByMonth[$key] ?? 0) + 1;
        }
        if ($relevantByMonth === []) {
            return [[], null];
        }

        /** @var array<string, int> $violationsByMonth */
        $violationsByMonth = [];
        $breaches = SlaViolation::query()
            ->whereBetween('breached_at', [$from, $to])
            ->when($filters->customerId !== null, fn($q) => $q->whereHas('serviceTicket', fn($t) => $t->where('customer_id', $filters->customerId)))
            ->when($excluded !== [], fn($q) => $q->whereDoesntHave('serviceTicket', fn($t) => $t->whereIn('customer_id', $excluded)))
            ->get(['breached_at']);
        foreach ($breaches as $violation) {
            $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $violation->breached_at))[0];
            $violationsByMonth[$key] = ($violationsByMonth[$key] ?? 0) + 1;
        }

        $series = [];
        foreach ($this->buildBucketsInRange($from, $to) as $bucket) {
            $relevant = $relevantByMonth[$bucket['key']] ?? 0;
            if ($relevant <= 0) {
                continue; // Ohne Bezugsmenge keine Quote ausweisbar.
            }
            $met = max(0, $relevant - ($violationsByMonth[$bucket['key']] ?? 0));
            $series[] = ['x' => $bucket['shortLabel'], 'y' => round($met / $relevant * 100, 1)];
        }

        $values = array_column($series, 'y');
        sort($values);
        $count = count($values);
        $median = $count > 0
            ? round(($values[intdiv($count - 1, 2)] + $values[intdiv($count, 2)]) / 2, 1)
            : null;

        return [$series, $median];
    }

    /**
     * Verletzungen je Kunde (Top 15) — Screen bar-h + PDF.
     *
     * @param  array<int, array{name:string, count:int}>  $byCustomer
     * @return list<array{x: string, y: int}>
     */
    private function violationsByCustomerSeries(array $byCustomer): array {
        return array_values(collect($byCustomer)
            ->filter(static fn(array $row): bool => $row['count'] > 0)
            ->take(15)
            ->map(static fn(array $row): array => ['x' => $row['name'], 'y' => $row['count']])
            ->all());
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $metrics, string $from, string $to, Request $request, array $exportFilters): Response {
        /** @var array<string, int> $byKind */
        $byKind = $metrics['by_kind'];
        /** @var array<string, int> $byPriority */
        $byPriority = $metrics['by_priority'];
        /** @var array<int, array{name:string, count:int}> $byCustomer */
        $byCustomer = $metrics['by_customer'];
        /** @var array<string, int> $byCause */
        $byCause = $metrics['by_cause'];
        /** @var float|null $rate */
        $rate = $metrics['compliance_rate'];

        $rows = [];
        $rows[] = [(string) __('sla.report.section'), (string) __('sla.report.metric'), (string) __('sla.report.value')];
        $rows[] = [(string) __('sla.report.overview'), (string) __('sla.report.total_tickets'), (string) $metrics['total_tickets']];
        $rows[] = [(string) __('sla.report.overview'), (string) __('sla.report.violations'), (string) $metrics['violation_count']];
        $rows[] = [
            (string) __('sla.report.overview'),
            (string) __('sla.report.compliance_rate'),
            $rate !== null ? NumberHelper::toUSFormat($rate * 100, 1) : '',
        ];
        foreach ($byKind as $kind => $count) {
            $rows[] = [(string) __('sla.report.by_kind'), $kind, (string) $count];
        }
        foreach ($byPriority as $prio => $count) {
            $rows[] = [(string) __('sla.report.by_priority'), $prio, (string) $count];
        }
        foreach ($byCustomer as $c) {
            $rows[] = [(string) __('sla.report.by_customer'), $c['name'], (string) $c['count']];
        }
        foreach ($byCause as $cause => $count) {
            $rows[] = [(string) __('sla.report.by_cause'), $cause, (string) $count];
        }

        return $this->csvWithMetadata(
            $rows,
            sprintf('sla_%s_%s.csv', $from, $to),
            'sla',
            $exportFilters,
            $request,
        );
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  list<array{x: string, y: int}>  $violationCustomerSeries
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $metrics, string $from, string $to, array $violationCustomerSeries, Request $request, array $exportFilters): SymfonyResponse {
        return $this->pdfDownload('reports.pdf.sla', array_merge($metrics, [
            'from' => $from,
            'to' => $to,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Verletzungen je Kunde (Top 15)'),
                'unit' => __('Verletzungen'),
                'xLabel' => __('Kunde'),
                'yLabel' => __('Anzahl'),
                'series' => $violationCustomerSeries,
            ],
        ]), sprintf('sla_%s_%s.pdf', $from, $to), request: $request, reportCode: 'sla', filters: $exportFilters);
    }
}
