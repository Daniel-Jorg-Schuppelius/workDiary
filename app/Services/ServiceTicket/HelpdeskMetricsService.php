<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskMetricsService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\{AuditLog, Change, KnowledgeArticle, KnowledgeArticleLink, Problem, ServiceRequest, ServiceTicket, SlaClockSegment, TicketSatisfaction};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Helpdesk-/Service-Desk-Kennzahlen (Feature 065, MVP-159) — Muster 064:
 * versionierte Definitionen (METRIC_VERSION), Berechnung aus Zeitstempeln
 * + sla_clock_segments (Pausen abgezogen). KEINE Agenten-Ranglisten —
 * Queue-Ebene ist die kleinste Aggregation (Vorgabe 065).
 *
 * v2 (MVP-159): agingHistogram (Altersbänder offener Tickets je Queue),
 * fcrAndReopens (FCR = gelöst OHNE reopened-/requeued-Audit; Wiederöffnungs-
 * und Weiterleitungsquote getrennt) und satisfaction (Ø, Verteilung 1–5,
 * Rücklaufquote = Antworten ÷ gelöste Tickets im Zeitraum).
 *
 * v3 (MVP-338, Bauturbo A20): recurringDespiteArticle — „Probleme trotz
 * Wissensartikel" (Feature 011): neue Incidents nach Artikel-Publikation.
 */
class HelpdeskMetricsService {
    public const METRIC_VERSION = 3;

    /**
     * Altersbänder in Tagen: Label → [einschließende Untergrenze,
     * ausschließende Obergrenze|null]. Einzige Wahrheit für Histogramm UND
     * Drilldown (identische Bandzuordnung, MVP-159).
     *
     * @var array<string, array{0: int, 1: int|null}>
     */
    public const AGING_BANDS = [
        '0-1' => [0, 1],
        '1-3' => [1, 3],
        '3-7' => [3, 7],
        '7-30' => [7, 30],
        '>30' => [30, null],
    ];

    /**
     * Offene Ticket-Status (alles außer isResolved) — geteilt mit den
     * Drilldowns, damit Histogramm und Trefferliste dieselbe Menge sehen.
     *
     * @return list<string>
     */
    public static function openStatuses(): array {
        return array_values(array_map(
            static fn(ServiceTicketStatus $status): string => $status->value,
            array_filter(ServiceTicketStatus::cases(), static fn(ServiceTicketStatus $status): bool => ! $status->isResolved()),
        ));
    }

    /**
     * Ticketvolumen je ISO-Woche und Queue (Zeitreihe).
     *
     * @return array<string, array<string, int>> Woche → Queue-Name → Anzahl
     */
    public function volumeByQueue(Carbon $from, Carbon $to): array {
        $rows = ServiceTicket::query()
            ->whereBetween('reported_at', [$from, $to])
            ->with('queue:id,name')
            ->get(['id', 'queue_id', 'reported_at']);

        $series = [];
        foreach ($rows as $ticket) {
            $week = $ticket->reported_at?->format('o-\WW') ?? '?';
            $queue = $ticket->queue->name ?? '—';
            $series[$week][$queue] = ($series[$week][$queue] ?? 0) + 1;
        }
        ksort($series);

        return $series;
    }

    /**
     * Reaktions-/Lösungszeiten in Stunden (P50/P85/P95) — Pausenzeiten aus
     * sla_clock_segments werden abgezogen (reproduzierbar).
     *
     * @return array{reaction: array<string, float|int>, resolution: array<string, float|int>}
     */
    public function responseTimes(Carbon $from, Carbon $to): array {
        $tickets = ServiceTicket::query()
            ->whereBetween('reported_at', [$from, $to])
            ->get(['id', 'reported_at', 'acknowledged_at', 'resolved_at']);

        $pausedByTicket = SlaClockSegment::query()
            ->whereIn('service_ticket_id', $tickets->pluck('id'))
            ->whereNotNull('paused_to')
            ->get()
            ->groupBy('service_ticket_id')
            ->map(fn($segments) => (float) $segments->sum(fn(SlaClockSegment $s): float => $s->paused_from->diffInMinutes($s->paused_to)));

        $reaction = [];
        $resolution = [];
        foreach ($tickets as $ticket) {
            $paused = (float) ($pausedByTicket[$ticket->id] ?? 0);
            if ($ticket->reported_at !== null && $ticket->acknowledged_at !== null) {
                $reaction[] = round(max(0, $ticket->reported_at->diffInMinutes($ticket->acknowledged_at) - $paused) / 60, 2);
            }
            if ($ticket->reported_at !== null && $ticket->resolved_at !== null) {
                $resolution[] = round(max(0, $ticket->reported_at->diffInMinutes($ticket->resolved_at) - $paused) / 60, 2);
            }
        }

        return [
            'reaction' => $this->percentiles($reaction),
            'resolution' => $this->percentiles($resolution),
        ];
    }

    /**
     * SLA-Erfüllung: Anteil ohne Fristverletzung (reaction/resolution).
     *
     * @return array{reaction_met: float, resolution_met: float, total: int}
     */
    public function slaCompliance(Carbon $from, Carbon $to): array {
        $withReaction = ServiceTicket::query()
            ->whereBetween('reported_at', [$from, $to])
            ->whereNotNull('reaction_due_at');
        $withResolution = ServiceTicket::query()
            ->whereBetween('reported_at', [$from, $to])
            ->whereNotNull('resolution_due_at');

        $reactionTotal = (clone $withReaction)->count();
        $resolutionTotal = (clone $withResolution)->count();

        return [
            'reaction_met' => $reactionTotal > 0
                ? round((1 - (clone $withReaction)->where('reaction_breached', true)->count() / $reactionTotal) * 100, 1)
                : 100.0,
            'resolution_met' => $resolutionTotal > 0
                ? round((1 - (clone $withResolution)->where('resolution_breached', true)->count() / $resolutionTotal) * 100, 1)
                : 100.0,
            'total' => ServiceTicket::query()->whereBetween('reported_at', [$from, $to])->count(),
        ];
    }

    /**
     * Wartezeiten nach Verursacher (Segment-Grund) in Stunden — nur
     * abgeschlossene Segmente (reproduzierbar).
     *
     * @return array<string, float>
     */
    public function waitingByReason(Carbon $from, Carbon $to): array {
        $result = [];
        foreach (SlaClockSegment::query()->whereBetween('paused_from', [$from, $to])->whereNotNull('paused_to')->get() as $segment) {
            $result[$segment->reason] = round(($result[$segment->reason] ?? 0) + $segment->paused_from->diffInMinutes($segment->paused_to) / 60, 1);
        }
        ksort($result);

        return $result;
    }

    /**
     * Change-Erfolgs-/Rollback-Quote (abgeschlossene Changes im Zeitraum).
     *
     * @return array<string, int>
     */
    public function changeOutcomes(Carbon $from, Carbon $to): array {
        return Change::query()
            ->where('status', 'done')
            ->whereBetween('updated_at', [$from, $to])
            ->get(['outcome'])
            ->countBy(fn(Change $change): string => (string) $change->outcome)
            ->sortKeys()
            ->all();
    }

    /**
     * Problem-/Known-Error-Bestand nach Status (Stichtag heute).
     *
     * @return array<string, int>
     */
    public function problemBacklog(): array {
        return Problem::query()
            ->get(['status'])
            ->countBy(fn(Problem $problem): string => $problem->status)
            ->sortKeys()
            ->all();
    }

    /**
     * „Probleme trotz Artikel" (Feature 011, MVP-338/Bauturbo A20): je
     * VERÖFFENTLICHTEM Wissensartikel mit Problem-Verknüpfung (Known
     * Error, {@see \App\Services\ServiceTicket\ProblemService::publishKnownError})
     * die neuen Incidents im Zeitraum, die NACH der Artikel-Publikation
     * gemeldet wurden (reported_at) — absteigend nach Vorkommen: die
     * Handlungsliste „Artikel unwirksam oder unauffindbar?". Trend =
     * zweite Zeitraum-Hälfte vs. erste (rising/falling/steady). Artikel
     * ohne neue Incidents erscheinen bewusst NICHT (kein Handlungsbedarf);
     * Tickets ohne reported_at fallen heraus (kein belastbares Auftreten,
     * analog agingHistogram). Einzige Datenquelle ist die Kette
     * Artikel↔Problem↔Incident-Pivot — direkte Ticket→Artikel-Verweise
     * existieren im Datenmodell nicht (LINKABLE_MAP ohne Ticket).
     *
     * @return list<array{article: KnowledgeArticle, problems: list<string>, count: int, first_half: int, second_half: int, trend: string, last_at: Carbon|null}>
     */
    public function recurringDespiteArticle(Carbon $from, Carbon $to): array {
        $problemMorph = (new Problem())->getMorphClass();

        // Problem-Verknüpfungen veröffentlichter Artikel — Mandantengrenze
        // transitiv über den org-gescopten Artikel (Allow-List Tenant-Audit).
        $links = KnowledgeArticleLink::query()
            ->where('linkable_type', $problemMorph)
            ->whereIn('knowledge_article_id', KnowledgeArticle::query()
                ->published()
                ->whereNotNull('published_at')
                ->select('id'))
            ->get(['knowledge_article_id', 'linkable_id']);
        if ($links->isEmpty()) {
            return [];
        }

        $articles = KnowledgeArticle::query()
            ->whereIn('id', $links->pluck('knowledge_article_id')->unique())
            ->get()
            ->keyBy('id');
        $problems = Problem::query()
            ->whereIn('id', $links->pluck('linkable_id')->unique())
            ->get(['id', 'title'])
            ->keyBy('id');

        // Incidents je Problem im Zeitraum; der Publikationsfilter ist je
        // Artikel verschieden und läuft im PHP-Nachgang (Report-Volumen).
        $pivot = DB::table('problem_ticket')
            ->whereIn('problem_id', $problems->keys()->all())
            ->get(['problem_id', 'service_ticket_id']);
        $tickets = ServiceTicket::query()
            ->whereIn('id', $pivot->pluck('service_ticket_id')->unique()->all())
            ->whereBetween('reported_at', [$from, $to])
            ->get(['id', 'reported_at'])
            ->keyBy('id');

        // diffInSeconds liefert float (Carbon 3) — für addSeconds runden.
        $mid = $from->copy()->addSeconds((int) ($from->diffInSeconds($to) / 2));

        $rows = [];
        foreach ($links->groupBy('knowledge_article_id') as $articleId => $group) {
            /** @var KnowledgeArticle|null $article */
            $article = $articles->get($articleId);
            if ($article === null || $article->published_at === null) {
                continue;
            }

            $problemIds = $group->pluck('linkable_id')->map(fn($id): int => (int) $id)->all();
            $ticketIds = $pivot
                ->whereIn('problem_id', $problemIds)
                ->pluck('service_ticket_id')
                ->unique();

            $count = 0;
            $firstHalf = 0;
            $lastAt = null;
            foreach ($ticketIds as $ticketId) {
                /** @var ServiceTicket|null $ticket */
                $ticket = $tickets->get((int) $ticketId);
                if ($ticket?->reported_at === null || ! $ticket->reported_at->isAfter($article->published_at)) {
                    continue;
                }
                $count++;
                if ($ticket->reported_at->isBefore($mid)) {
                    $firstHalf++;
                }
                if ($lastAt === null || $ticket->reported_at->isAfter($lastAt)) {
                    $lastAt = $ticket->reported_at;
                }
            }
            if ($count === 0) {
                continue;
            }

            $secondHalf = $count - $firstHalf;
            $rows[] = [
                'article' => $article,
                'problems' => array_values($problems->only($problemIds)->pluck('title')
                    ->map(fn(mixed $title): string => (string) $title)
                    ->sort()->all()),
                'count' => $count,
                'first_half' => $firstHalf,
                'second_half' => $secondHalf,
                'trend' => $secondHalf <=> $firstHalf,
                'last_at' => $lastAt,
            ];
        }

        usort($rows, static fn(array $a, array $b): int => [$b['count'], $b['last_at']] <=> [$a['count'], $a['last_at']]);

        return array_map(static fn(array $row): array => [
            ...$row,
            'trend' => match ($row['trend']) {
                1 => 'rising',
                -1 => 'falling',
                default => 'steady',
            },
        ], $rows);
    }

    /**
     * Katalog-Nachfrage: Requests je Katalogeintrag mit Median-Dauern
     * (Genehmigung = erste Entscheidung, Erfüllung = done) in Stunden.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogDemand(Carbon $from, Carbon $to): array {
        $requests = ServiceRequest::query()
            ->whereBetween('created_at', [$from, $to])
            ->with('approvals')
            ->get();

        $byItem = [];
        foreach ($requests as $request) {
            $name = (string) ($request->catalog_snapshot['name'] ?? '?');
            $byItem[$name] ??= ['count' => 0, 'approval_hours' => [], 'fulfillment_hours' => []];
            $byItem[$name]['count']++;
            $createdAt = $request->created_at;
            $firstDecision = $request->approvals->whereNotNull('decided_at')->min('decided_at');
            if ($firstDecision !== null && $createdAt !== null) {
                $byItem[$name]['approval_hours'][] = round($createdAt->diffInMinutes($firstDecision) / 60, 2);
            }
            if ($request->status === ServiceRequest::STATUS_DONE && $createdAt !== null && $request->updated_at !== null) {
                $byItem[$name]['fulfillment_hours'][] = round($createdAt->diffInMinutes($request->updated_at) / 60, 2);
            }
        }

        return collect($byItem)->map(fn(array $row, string $name): array => [
            'item' => $name,
            'count' => $row['count'],
            'approval_median_hours' => $this->median($row['approval_hours']),
            'fulfillment_median_hours' => $this->median($row['fulfillment_hours']),
        ])->values()->all();
    }

    /**
     * Aging-Histogramm: OFFENE Tickets (Status nicht isResolved) in
     * Altersbändern 0–1/1–3/3–7/7–30/>30 Tage je Queue — Alter ab
     * reported_at (Tickets ohne reported_at fallen bewusst heraus,
     * sie haben kein belastbares Alter). Alle Bänder sind immer
     * vorhanden (stabile Reihenfolge fürs Diagramm).
     *
     * @return array<string, array{total: int, queues: array<string, int>}>
     */
    public function agingHistogram(?Carbon $now = null): array {
        $now ??= now();

        $bands = [];
        foreach (array_keys(self::AGING_BANDS) as $band) {
            $bands[$band] = ['total' => 0, 'queues' => []];
        }

        $tickets = ServiceTicket::query()
            ->whereIn('status', self::openStatuses())
            ->whereNotNull('reported_at')
            ->with('queue:id,name')
            ->get(['id', 'queue_id', 'status', 'reported_at']);

        foreach ($tickets as $ticket) {
            if ($ticket->reported_at === null) {
                continue;
            }
            $band = $this->agingBand($ticket->reported_at, $now);
            $queue = $ticket->queue->name ?? '—';
            $bands[$band]['total']++;
            $bands[$band]['queues'][$queue] = ($bands[$band]['queues'][$queue] ?? 0) + 1;
        }

        foreach ($bands as &$row) {
            ksort($row['queues']);
        }
        unset($row);

        return $bands;
    }

    /** Bandzuordnung eines Meldezeitpunkts (geteilt mit dem Drilldown). */
    public function agingBand(Carbon $reportedAt, Carbon $now): string {
        $ageDays = $reportedAt->diffInMinutes($now) / 1440;
        foreach (self::AGING_BANDS as $band => [, $max]) {
            if ($max === null || $ageDays < $max) {
                return $band;
            }
        }

        return array_key_last(self::AGING_BANDS);
    }

    /**
     * First Contact Resolution + Wiederöffnungs-/Weiterleitungsquote:
     * Basis sind Tickets mit resolved_at im Zeitraum. FCR = gelöst OHNE
     * `service_ticket.reopened`- UND OHNE `service_ticket.requeued`-Audit;
     * beide Quoten werden getrennt ausgewiesen. Kleinste Aggregation ist
     * die Queue — bewusst KEINE Agenten-Dimension (Vorgabe 065). Die
     * Audit-Zählung läuft gechunkt über whereIn(auditable_id).
     *
     * @return array{total: int, fcr: int, fcr_rate: float, reopened: int, reopened_rate: float, requeued: int, requeued_rate: float, queues: array<string, array{total: int, fcr: int, reopened: int, requeued: int, fcr_rate: float}>}
     */
    public function fcrAndReopens(Carbon $from, Carbon $to): array {
        $tickets = ServiceTicket::query()
            ->whereBetween('resolved_at', [$from, $to])
            ->with('queue:id,name')
            ->get(['id', 'queue_id', 'resolved_at']);

        $reopenedIds = [];
        $requeuedIds = [];
        foreach ($tickets->pluck('id')->chunk(500) as $ids) {
            $logs = AuditLog::query()
                ->where('auditable_type', ServiceTicket::class)
                ->whereIn('auditable_id', $ids->all())
                ->whereIn('event', ['service_ticket.reopened', 'service_ticket.requeued'])
                ->get(['auditable_id', 'event']);
            foreach ($logs as $log) {
                if ((string) $log->getAttribute('event') === 'service_ticket.reopened') {
                    $reopenedIds[(int) $log->getAttribute('auditable_id')] = true;
                } else {
                    $requeuedIds[(int) $log->getAttribute('auditable_id')] = true;
                }
            }
        }

        $queues = [];
        $fcr = 0;
        $reopened = 0;
        $requeued = 0;
        foreach ($tickets as $ticket) {
            $queue = $ticket->queue->name ?? '—';
            $queues[$queue] ??= ['total' => 0, 'fcr' => 0, 'reopened' => 0, 'requeued' => 0];
            $queues[$queue]['total']++;
            $wasReopened = isset($reopenedIds[(int) $ticket->id]);
            $wasRequeued = isset($requeuedIds[(int) $ticket->id]);
            if ($wasReopened) {
                $reopened++;
                $queues[$queue]['reopened']++;
            }
            if ($wasRequeued) {
                $requeued++;
                $queues[$queue]['requeued']++;
            }
            if (! $wasReopened && ! $wasRequeued) {
                $fcr++;
                $queues[$queue]['fcr']++;
            }
        }
        ksort($queues);

        $rate = static fn(int $part, int $total): float => $total > 0 ? round($part / $total * 100, 1) : 0.0;
        $queues = array_map(
            static fn(array $row): array => [...$row, 'fcr_rate' => $rate($row['fcr'], $row['total'])],
            $queues,
        );

        $total = $tickets->count();

        return [
            'total' => $total,
            'fcr' => $fcr,
            'fcr_rate' => $rate($fcr, $total),
            'reopened' => $reopened,
            'reopened_rate' => $rate($reopened, $total),
            'requeued' => $requeued,
            'requeued_rate' => $rate($requeued, $total),
            'queues' => $queues,
        ];
    }

    /**
     * Zufriedenheit: Ø-Score, Verteilung 1–5 (immer alle Stufen) und
     * Rücklaufquote = Antworten (answered_at im Zeitraum) ÷ gelöste
     * Tickets (resolved_at im Zeitraum).
     *
     * @return array{average: float, distribution: array<int, int>, responses: int, closed_total: int, response_rate: float}
     */
    public function satisfaction(Carbon $from, Carbon $to): array {
        $scores = TicketSatisfaction::query()
            ->whereBetween('answered_at', [$from, $to])
            ->pluck('score');

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($scores as $score) {
            $key = (int) $score;
            if (isset($distribution[$key])) {
                $distribution[$key]++;
            }
        }

        $responses = $scores->count();
        $closedTotal = ServiceTicket::query()->whereBetween('resolved_at', [$from, $to])->count();

        return [
            'average' => $responses > 0 ? round((float) $scores->sum() / $responses, 2) : 0.0,
            'distribution' => $distribution,
            'responses' => $responses,
            'closed_total' => $closedTotal,
            'response_rate' => $closedTotal > 0 ? round($responses / $closedTotal * 100, 1) : 0.0,
        ];
    }

    /**
     * @param array<int, float> $values
     * @return array{p50: float, p85: float, p95: float, count: int}
     */
    private function percentiles(array $values): array {
        $p = function (array $values, int $percentile): float {
            if ($values === []) {
                return 0.0;
            }
            sort($values);

            return (float) $values[max(0, (int) ceil($percentile / 100 * count($values)) - 1)];
        };

        return ['p50' => $p($values, 50), 'p85' => $p($values, 85), 'p95' => $p($values, 95), 'count' => count($values)];
    }

    /** @param array<int, float> $values */
    private function median(array $values): float {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1 ? (float) $values[$middle] : round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 1);
    }
}
