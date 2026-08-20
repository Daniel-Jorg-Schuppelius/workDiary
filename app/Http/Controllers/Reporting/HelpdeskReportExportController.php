<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskReportExportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{AuditLog, KnowledgeArticle, KnowledgeArticleLink, Problem, ServiceQueue, ServiceTicket, SlaClockSegment, TicketSatisfaction};
use App\Services\ServiceTicket\HelpdeskMetricsService;
use App\Support\Sqid;
use Illuminate\Http\{Request, Response};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Drilldowns und Exporte des Helpdesk-Berichts (Feature 065, MVP-159) —
 * Muster 064 (AgileReportExportController): Drilldown NUR über signierten,
 * kurzlebigen Link PLUS Report-Recht mit sichtbarer Summen-Konsistenz-
 * prüfung; CSV mit Exportkopf (Reportcode, Filter, metric_version,
 * Berechnungsstand, Einheit) und Audit report.exported; PDF über
 * RendersReportPdf (pdf-toolkit).
 *
 * Rechte-Entscheidung: Es bleibt bewusst bei `sla.viewAny` — dasselbe
 * Recht wie die Berichtsseite selbst (HelpdeskReportController). Ein
 * eigenes Export-Recht brächte keine zusätzliche Trennschärfe, weil
 * Drilldown/CSV/PDF nur Daten zeigen, die die Berichtsseite ohnehin
 * aggregiert ausweist; die Signaturpflicht verhindert das Teilen roher
 * Drilldown-Links (Whitebox-Leitplanke Export-Authz).
 */
class HelpdeskReportExportController extends Controller {
    use RendersReportPdf;

    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    private const CSV_METRICS = ['volume', 'times', 'compliance', 'waiting', 'aging', 'fcr', 'satisfaction', 'changes', 'problems', 'catalog', 'knowledge'];

    public function __construct(private readonly HelpdeskMetricsService $metrics) {}

    /**
     * Drilldown hinter einem Datenpunkt: kind ∈ volume_week_queue |
     * aging_band | waiting_reason | reopened | satisfaction_score |
     * knowledge_recurrence (MVP-338: neue Incidents trotz Artikel).
     * `expected` trägt den Kennzahlwert des Punktes — weicht die
     * Trefferzahl ab, erscheint ein sichtbarer Hinweis. Alle Listen sind
     * org-gescopt (OrganizationScope) und paginiert; der Seitenwechsel
     * bleibt signiert, weil `page` von der Signaturprüfung ausgenommen ist.
     */
    public function drilldown(Request $request): View {
        abort_unless($request->hasValidSignatureWhileIgnoring(['page']), 403);
        Gate::authorize(Permission::SlaViewAny->value);

        $kind = (string) $request->query('kind');
        $key = (string) $request->query('key');
        $expected = (int) $request->query('expected', '0');
        [$from, $to] = $this->period($request);

        [$title, $rows] = match ($kind) {
            'volume_week_queue' => $this->volumeRows($request, $key),
            'aging_band' => $this->agingRows($key),
            'waiting_reason' => $this->waitingRows($key, $from, $to),
            'reopened' => $this->reopenedRows($key, $from, $to),
            'satisfaction_score' => $this->satisfactionRows($key, $from, $to),
            'knowledge_recurrence' => $this->knowledgeRecurrenceRows($key, $from, $to),
            default => abort(404),
        };

        return view('helpdesk.reports.drilldown', [
            'title' => $title,
            'kind' => $kind,
            'key' => $key,
            'rows' => $rows,
            'expected' => $expected,
            'consistent' => $rows->total() === $expected,
        ]);
    }

    /** CSV der Berichts-Rohdaten einer Kennzahl (Exportkopf + Audit). */
    public function csv(Request $request, string $metric): Response {
        Gate::authorize(Permission::SlaViewAny->value);
        abort_unless(in_array($metric, self::CSV_METRICS, true), 404);

        [$from, $to] = $this->period($request);
        $filters = ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'metric' => $metric];

        $rows = [
            ['metric_version', (string) HelpdeskMetricsService::METRIC_VERSION],
            ['unit', $this->csvUnit($metric)],
            ['computed_at', now()->toIso8601String()],
            [''],
            ...$this->csvRows($metric, $from, $to),
        ];

        // A9: Audit läuft in csvWithMetadata; Audit-Code bleibt ohne Versions-Suffix.
        return $this->csvWithMetadata(
            $rows,
            sprintf('helpdesk_%s_%s.csv', $metric, now()->format('Ymd')),
            'helpdesk_' . $metric . '_v' . HelpdeskMetricsService::METRIC_VERSION,
            $filters,
            $request,
            'helpdesk_' . $metric,
        );
    }

    /** Helpdesk-Bericht als PDF (Kennzahlen-Tabellen, pdf-toolkit). */
    public function pdf(Request $request): SymfonyResponse {
        Gate::authorize(Permission::SlaViewAny->value);

        [$from, $to] = $this->period($request);
        $filters = ['from' => $from->toDateString(), 'to' => $to->toDateString()];

        return $this->pdfDownload('helpdesk.reports.pdf', [
            'from' => $from,
            'to' => $to,
            'metricVersion' => HelpdeskMetricsService::METRIC_VERSION,
            'compliance' => $this->metrics->slaCompliance($from, $to),
            'times' => $this->metrics->responseTimes($from, $to),
            'fcr' => $this->metrics->fcrAndReopens($from, $to),
            'aging' => $this->metrics->agingHistogram(),
            'satisfaction' => $this->metrics->satisfaction($from, $to),
        ], sprintf('helpdesk_bericht_%s.pdf', now()->format('Ymd')), request: $request, reportCode: 'helpdesk_report', filters: $filters);
    }

    /**
     * Zeitraum wie auf der Berichtsseite (Default letzte 8 Wochen).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array {
        // W2.1: einheitlicher Parameter-Guard, fachlicher 8-Wochen-Default bleibt
        // (identisch zur Berichtsseite, damit Export und Ansicht deckungsgleich sind).
        [$from, $to] = $this->resolveRangeWithDefault($request, static fn (): array => [
            \Carbon\CarbonImmutable::now()->subWeeks(8)->startOfWeek(),
            \Carbon\CarbonImmutable::now(),
        ]);

        return [Carbon::instance($from->toDateTime()), Carbon::instance($to->toDateTime())];
    }

    /** @return array{0: string, 1: LengthAwarePaginator<int, array<string, mixed>>} */
    private function volumeRows(Request $request, string $week): array {
        abort_unless(preg_match('/^(\d{4})-W(\d{2})$/', $week, $matches) === 1, 404);
        $start = Carbon::now()->setISODate((int) $matches[1], (int) $matches[2])->startOfWeek();
        $end = $start->copy()->endOfWeek();

        // Optionale Queue-Dimension (Sqid, strikt mit Zielklasse dekodiert).
        $queueSqid = (string) $request->query('queue', '');
        $queueId = Sqid::decode(ServiceQueue::class, $queueSqid !== '' ? $queueSqid : null);

        /** @var LengthAwarePaginator<int, array<string, mixed>> $rows */
        $rows = ServiceTicket::query()
            ->whereBetween('reported_at', [$start, $end])
            ->when($queueId !== null, fn($query) => $query->where('queue_id', $queueId))
            ->with('queue:id,name')
            ->orderBy('reported_at')->orderBy('id')
            ->paginate(50)->withQueryString()
            ->through(fn(ServiceTicket $ticket): array => [
                'ticket' => $ticket,
                'at' => $ticket->reported_at?->isoFormat('L LT'),
                'detail' => $ticket->queue->name ?? '—',
            ]);

        return [(string) __('Tickets in Woche :week', ['week' => $week]), $rows];
    }

    /** @return array{0: string, 1: LengthAwarePaginator<int, array<string, mixed>>} */
    private function agingRows(string $band): array {
        abort_unless(array_key_exists($band, HelpdeskMetricsService::AGING_BANDS), 404);
        [$min, $max] = HelpdeskMetricsService::AGING_BANDS[$band];
        $now = now();

        // Identische Bandgrenzen wie agingHistogram(): min ≤ Alter < max.
        /** @var LengthAwarePaginator<int, array<string, mixed>> $rows */
        $rows = ServiceTicket::query()
            ->whereIn('status', HelpdeskMetricsService::openStatuses())
            ->whereNotNull('reported_at')
            ->when($min > 0, fn($query) => $query->where('reported_at', '<=', $now->copy()->subDays($min)))
            ->when($max !== null, fn($query) => $query->where('reported_at', '>', $now->copy()->subDays((int) $max)))
            ->with('queue:id,name')
            ->orderBy('reported_at')->orderBy('id')
            ->paginate(50)->withQueryString()
            ->through(fn(ServiceTicket $ticket): array => [
                'ticket' => $ticket,
                'at' => $ticket->reported_at?->isoFormat('L LT'),
                'detail' => __(':days Tage offen', ['days' => $ticket->reported_at !== null ? round($ticket->reported_at->diffInMinutes($now) / 1440, 1) : '—']),
            ]);

        return [(string) __('Offene Tickets im Altersband :band Tage', ['band' => $band]), $rows];
    }

    /** @return array{0: string, 1: LengthAwarePaginator<int, array<string, mixed>>} */
    private function waitingRows(string $reason, Carbon $from, Carbon $to): array {
        /** @var LengthAwarePaginator<int, array<string, mixed>> $rows */
        $rows = SlaClockSegment::query()
            ->where('reason', $reason)
            ->whereBetween('paused_from', [$from, $to])
            ->whereNotNull('paused_to')
            ->with('ticket.queue')
            ->orderBy('paused_from')->orderBy('id')
            ->paginate(50)->withQueryString()
            ->through(fn(SlaClockSegment $segment): array => [
                'ticket' => $segment->ticket,
                'at' => $segment->paused_from->isoFormat('L LT'),
                'detail' => __(':hours h pausiert', ['hours' => $segment->paused_to !== null ? round($segment->paused_from->diffInMinutes($segment->paused_to) / 60, 1) : '—']),
            ]);

        return [(string) __('Wartezeiten mit Grund „:reason"', ['reason' => $reason]), $rows];
    }

    /** @return array{0: string, 1: LengthAwarePaginator<int, array<string, mixed>>} */
    private function reopenedRows(string $key, Carbon $from, Carbon $to): array {
        abort_unless(in_array($key, ['reopened', 'requeued'], true), 404);

        /** @var LengthAwarePaginator<int, array<string, mixed>> $rows */
        $rows = ServiceTicket::query()
            ->whereBetween('resolved_at', [$from, $to])
            ->whereIn('id', AuditLog::query()
                ->select('auditable_id')
                ->where('auditable_type', ServiceTicket::class)
                ->where('event', 'service_ticket.' . $key))
            ->with('queue:id,name')
            ->orderBy('resolved_at')->orderBy('id')
            ->paginate(50)->withQueryString()
            ->through(fn(ServiceTicket $ticket): array => [
                'ticket' => $ticket,
                'at' => $ticket->resolved_at?->isoFormat('L LT'),
                'detail' => $key === 'reopened' ? (string) __('Wiedereröffnet') : (string) __('Weitergeleitet (Queue-Wechsel)'),
            ]);

        $title = $key === 'reopened'
            ? (string) __('Gelöste Tickets mit Wiedereröffnung')
            : (string) __('Gelöste Tickets mit Weiterleitung');

        return [$title, $rows];
    }

    /** @return array{0: string, 1: LengthAwarePaginator<int, array<string, mixed>>} */
    private function satisfactionRows(string $key, Carbon $from, Carbon $to): array {
        abort_unless(in_array($key, ['1', '2', '3', '4', '5'], true), 404);

        /** @var LengthAwarePaginator<int, array<string, mixed>> $rows */
        $rows = TicketSatisfaction::query()
            ->where('score', (int) $key)
            ->whereBetween('answered_at', [$from, $to])
            ->with('ticket.queue')
            ->orderBy('answered_at')->orderBy('id')
            ->paginate(50)->withQueryString()
            ->through(fn(TicketSatisfaction $entry): array => [
                'ticket' => $entry->ticket,
                'at' => $entry->answered_at->isoFormat('L LT'),
                'detail' => $entry->comment ?? '—',
            ]);

        return [(string) __('Bewertungen mit Score :score', ['score' => $key]), $rows];
    }

    /**
     * MVP-338 (Feature 011, Bauturbo A20): Incidents, die NACH der
     * Publikation des Wissensartikels gemeldet wurden (identische
     * Definition wie {@see HelpdeskMetricsService::recurringDespiteArticle}:
     * reported_at im Zeitraum UND nach published_at, Kette
     * Artikel↔Problem↔Incident-Pivot). Key = Artikel-Sqid, strikt mit
     * Zielklasse dekodiert; Org-Scope hart über findOrFail (Global Scope).
     *
     * @return array{0: string, 1: LengthAwarePaginator<int, array<string, mixed>>}
     */
    private function knowledgeRecurrenceRows(string $key, Carbon $from, Carbon $to): array {
        $articleId = Sqid::decodeOrNumeric(KnowledgeArticle::class, $key);
        abort_if($articleId === null || $articleId < 1, 404);

        /** @var KnowledgeArticle $article */
        $article = KnowledgeArticle::query()->whereNotNull('published_at')->findOrFail($articleId);

        $problemIds = KnowledgeArticleLink::query()
            ->where('knowledge_article_id', $article->id)
            ->where('linkable_type', (new Problem())->getMorphClass())
            ->pluck('linkable_id');
        abort_if($problemIds->isEmpty(), 404);

        /** @var LengthAwarePaginator<int, array<string, mixed>> $rows */
        $rows = ServiceTicket::query()
            ->whereIn('id', DB::table('problem_ticket')
                ->whereIn('problem_id', $problemIds->all())
                ->select('service_ticket_id'))
            ->whereBetween('reported_at', [$from, $to])
            ->where('reported_at', '>', $article->published_at)
            ->with('queue:id,name')
            ->orderBy('reported_at')->orderBy('id')
            ->paginate(50)->withQueryString()
            ->through(fn(ServiceTicket $ticket): array => [
                'ticket' => $ticket,
                'at' => $ticket->reported_at?->isoFormat('L LT'),
                'detail' => __('Gemeldet nach Publikation am :date', ['date' => $article->published_at?->isoFormat('L')]),
            ]);

        return [(string) __('Neue Incidents trotz Artikel „:title"', ['title' => $article->title]), $rows];
    }

    private function csvUnit(string $metric): string {
        return match ($metric) {
            'volume' => 'tickets_per_week',
            'times' => 'hours',
            'compliance', 'fcr' => 'percent',
            'waiting' => 'hours',
            'aging' => 'tickets',
            'satisfaction' => 'score_1_5',
            'changes' => 'changes',
            'problems' => 'problems',
            'knowledge' => 'tickets',
            default => 'requests',
        };
    }

    /**
     * Diagramm-/Tabellen-Rohdaten je Kennzahl als CSV-Zeilen.
     *
     * @return list<list<string|int|float|null>>
     */
    private function csvRows(string $metric, Carbon $from, Carbon $to): array {
        return match ($metric) {
            'volume' => [
                ['week', 'queue', 'tickets'],
                ...collect($this->metrics->volumeByQueue($from, $to))
                    ->flatMap(fn(array $queues, string $week): array => collect($queues)
                        ->map(fn(int $count, string $queue): array => [$week, $queue, $count])
                        ->values()->all())
                    ->values()->all(),
            ],
            'times' => [
                ['target', 'p50', 'p85', 'p95', 'count'],
                ...collect($this->metrics->responseTimes($from, $to))
                    ->map(fn(array $row, string $target): array => [$target, $row['p50'], $row['p85'], $row['p95'], $row['count']])
                    ->values()->all(),
            ],
            'compliance' => [
                ['reaction_met_percent', 'resolution_met_percent', 'total'],
                array_values($this->metrics->slaCompliance($from, $to)),
            ],
            'waiting' => [
                ['reason', 'hours'],
                ...collect($this->metrics->waitingByReason($from, $to))
                    ->map(fn(float $hours, string $reason): array => [$reason, $hours])
                    ->values()->all(),
            ],
            'aging' => [
                ['band_days', 'queue', 'tickets'],
                ...collect($this->metrics->agingHistogram())
                    ->flatMap(fn(array $row, string $band): array => $row['queues'] === []
                        ? [[$band, '—', 0]]
                        : collect($row['queues'])->map(fn(int $count, string $queue): array => [$band, $queue, $count])->values()->all())
                    ->values()->all(),
            ],
            'fcr' => $this->fcrCsvRows($from, $to),
            'satisfaction' => $this->satisfactionCsvRows($from, $to),
            'changes' => [
                ['outcome', 'count'],
                ...collect($this->metrics->changeOutcomes($from, $to))
                    ->map(fn(int $count, string $outcome): array => [$outcome, $count])
                    ->values()->all(),
            ],
            'problems' => [
                ['status', 'count'],
                ...collect($this->metrics->problemBacklog())
                    ->map(fn(int $count, string $status): array => [$status, $count])
                    ->values()->all(),
            ],
            // MVP-338: „Probleme trotz Wissensartikel" (Feature 011).
            'knowledge' => [
                ['article', 'published_at', 'problems', 'incidents_after_publication', 'first_half', 'second_half', 'trend', 'last_occurrence'],
                ...array_map(
                    static fn(array $row): array => [
                        (string) $row['article']->title,
                        $row['article']->published_at?->toDateString(),
                        implode(' | ', $row['problems']),
                        (int) $row['count'],
                        (int) $row['first_half'],
                        (int) $row['second_half'],
                        (string) $row['trend'],
                        $row['last_at']?->toIso8601String(),
                    ],
                    $this->metrics->recurringDespiteArticle($from, $to),
                ),
            ],
            default => [
                ['item', 'count', 'approval_median_hours', 'fulfillment_median_hours'],
                ...array_map(
                    fn(array $row): array => [(string) $row['item'], (int) $row['count'], (float) $row['approval_median_hours'], (float) $row['fulfillment_median_hours']],
                    $this->metrics->catalogDemand($from, $to),
                ),
            ],
        };
    }

    /** @return list<list<string|int|float|null>> */
    private function fcrCsvRows(Carbon $from, Carbon $to): array {
        $fcr = $this->metrics->fcrAndReopens($from, $to);

        return [
            ['queue', 'resolved', 'fcr', 'fcr_rate_percent', 'reopened', 'requeued'],
            ...collect($fcr['queues'])
                ->map(fn(array $row, string $queue): array => [$queue, $row['total'], $row['fcr'], $row['fcr_rate'], $row['reopened'], $row['requeued']])
                ->values()->all(),
            ['(total)', $fcr['total'], $fcr['fcr'], $fcr['fcr_rate'], $fcr['reopened'], $fcr['requeued']],
        ];
    }

    /** @return list<list<string|int|float|null>> */
    private function satisfactionCsvRows(Carbon $from, Carbon $to): array {
        $satisfaction = $this->metrics->satisfaction($from, $to);

        return [
            ['score', 'responses'],
            ...collect($satisfaction['distribution'])
                ->map(fn(int $count, int $score): array => [$score, $count])
                ->values()->all(),
            [''],
            ['average', $satisfaction['average']],
            ['responses', $satisfaction['responses']],
            ['closed_total', $satisfaction['closed_total']],
            ['response_rate_percent', $satisfaction['response_rate']],
        ];
    }
}
