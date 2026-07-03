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
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{ServiceTicket, SlaViolation, User};
use App\Services\Reporting\ReportTargetEvaluator;
use App\Services\ServiceTicket\SlaViolationService;
use Carbon\Carbon;
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
    use WritesReportCsv;

    public function __construct(private readonly ReportTargetEvaluator $targets) {}

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize('viewAny', SlaViolation::class);

        $range = $this->globalDateRange();
        $fromDate = Carbon::parse($range['from']->toDateString())->startOfDay();
        $toDate = Carbon::parse($range['to']->toDateString())->endOfDay();
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $metrics = $this->aggregate($fromDate, $toDate);

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

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($metrics, $from, $to);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($metrics, $from, $to);
        }

        return view('reports.sla', array_merge($metrics, [
            'from' => $from,
            'to' => $to,
            'canManage' => Gate::allows('acknowledge', new SlaViolation),
        ]));
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
    private function aggregate(Carbon $from, Carbon $to): array {
        // Tickets mit Lösungsfrist im Zeitraum (gemeldet im Zeitraum) bilden die
        // Bezugsmenge für die Einhaltungsquote.
        $relevant = ServiceTicket::query()
            ->whereNotNull('resolution_due_at')
            ->whereBetween('reported_at', [$from, $to])
            ->count();

        /** @var Collection<int, SlaViolation> $violations */
        $violations = SlaViolation::query()
            ->with(['serviceTicket:id,ticket_no,title,customer_id,status', 'serviceTicket.customer:id,name'])
            ->whereBetween('breached_at', [$from, $to])
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
            'compliance_rate' => $relevant > 0 ? $met / $relevant : null,
            'by_kind' => $byKind,
            'by_priority' => $byPriority,
            'by_customer' => array_values($byCustomer),
            'by_cause' => $byCause,
            'violations' => $violations,
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function exportCsv(array $metrics, string $from, string $to): Response {
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
            $rate !== null ? number_format($rate * 100, 1, '.', '') : '',
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
            ['from' => $from, 'to' => $to],
        );
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function exportPdf(array $metrics, string $from, string $to): SymfonyResponse {
        return $this->pdfDownload('reports.pdf.sla', array_merge($metrics, [
            'from' => $from,
            'to' => $to,
        ]), sprintf('sla_%s_%s.pdf', $from, $to));
    }
}
