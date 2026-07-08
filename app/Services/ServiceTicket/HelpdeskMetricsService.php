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

use App\Models\{Change, Problem, ServiceRequest, ServiceTicket, SlaClockSegment};
use Illuminate\Support\Carbon;

/**
 * Helpdesk-/Service-Desk-Kennzahlen (Feature 065, MVP-159) — Muster 064:
 * versionierte Definitionen (METRIC_VERSION), Berechnung aus Zeitstempeln
 * + sla_clock_segments (Pausen abgezogen). KEINE Agenten-Ranglisten —
 * Queue-Ebene ist die kleinste Aggregation (Vorgabe 065).
 */
class HelpdeskMetricsService {
    public const METRIC_VERSION = 1;

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
