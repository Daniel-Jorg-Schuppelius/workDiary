<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\SlaClockSegment;
use App\Services\ServiceTicket\HelpdeskMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Gate, URL};
use Illuminate\View\View;

/**
 * Helpdesk-/Service-Desk-Bericht (Feature 065, MVP-159): Volumen,
 * Reaktions-/Lösungszeiten, SLA-Erfüllung, Wartezeiten, FCR/Wiederöffnung/
 * Weiterleitung, Aging-Histogramm, Zufriedenheit, Change-Quoten,
 * Problem-Bestand, Katalog-Nachfrage — Rendering über die x-charts.*-
 * Komponenten aus Feature 064. Keine Agenten-Ranglisten (Vorgabe).
 * Drilldown-Links sind signiert + kurzlebig (Muster 064, P11).
 *
 * MVP-338 (Feature 011, Bauturbo A20): Sektion „Probleme trotz
 * Wissensartikel" — neue Incidents nach Artikel-Publikation. Bewusst HIER
 * statt auf einer eigenen Wissensbasis-Seite: die Wissensbasis-UI hat
 * keinen Berichts-Platz, die Datenbasis (Problem↔Incidents) ist
 * Helpdesk-Domäne und Drilldown-/Export-/Rechte-Infrastruktur existiert
 * in diesem Bericht bereits (sla.viewAny).
 */
class HelpdeskReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request, HelpdeskMetricsService $metrics): View {
        Gate::authorize(Permission::SlaViewAny->value);

        // W2.1: einheitlicher Parameter-Guard, fachlicher 8-Wochen-Default bleibt.
        [$rangeFrom, $rangeTo] = $this->resolveRangeWithDefault($request, static fn (): array => [
            \Carbon\CarbonImmutable::now()->subWeeks(8)->startOfWeek(),
            \Carbon\CarbonImmutable::now(),
        ]);
        $from = Carbon::instance($rangeFrom->toDateTime());
        $to = Carbon::instance($rangeTo->toDateTime());

        // Signierte, kurzlebige Drilldown-Links (P11-Muster);
        // expected = Kennzahlwert des Punktes für die Konsistenzprüfung.
        $drill = fn(array $params): string => URL::temporarySignedRoute(
            'helpdesk.reports.drilldown',
            now()->addMinutes(30),
            [...$params, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
        );

        $volume = $metrics->volumeByQueue($from, $to);
        $waiting = $metrics->waitingByReason($from, $to);
        $aging = $metrics->agingHistogram();
        $fcr = $metrics->fcrAndReopens($from, $to);
        $satisfaction = $metrics->satisfaction($from, $to);

        // Segment-Anzahl je Wartegrund — Erwartungswert des Drilldowns
        // (die Kennzahl selbst bleibt Stunden).
        $waitingCounts = SlaClockSegment::query()
            ->whereBetween('paused_from', [$from, $to])
            ->whereNotNull('paused_to')
            ->get(['reason'])
            ->countBy('reason');

        return view('helpdesk.reports.index', [
            'from' => $from,
            'to' => $to,
            'metricVersion' => HelpdeskMetricsService::METRIC_VERSION,
            'volume' => $volume,
            'volumeSeries' => collect($volume)->map(fn(array $queues, string $week): array => [
                'x' => $week,
                'y' => array_sum($queues),
                'url' => $week !== '?' ? $drill(['kind' => 'volume_week_queue', 'key' => $week, 'expected' => array_sum($queues)]) : null,
            ])->values()->all(),
            'times' => $metrics->responseTimes($from, $to),
            'compliance' => $metrics->slaCompliance($from, $to),
            'waiting' => $waiting,
            'waitingSeries' => collect($waiting)->map(fn(float $hours, string $reason): array => [
                'x' => $reason,
                'y' => $hours,
                'url' => $drill(['kind' => 'waiting_reason', 'key' => $reason, 'expected' => (int) ($waitingCounts[$reason] ?? 0)]),
            ])->values()->all(),
            'aging' => $aging,
            'agingSeries' => collect($aging)->map(fn(array $row, string $band): array => [
                'x' => $band,
                'y' => $row['total'],
                'url' => $drill(['kind' => 'aging_band', 'key' => $band, 'expected' => $row['total']]),
            ])->values()->all(),
            'fcr' => $fcr,
            'reopenedUrl' => $drill(['kind' => 'reopened', 'key' => 'reopened', 'expected' => $fcr['reopened']]),
            'requeuedUrl' => $drill(['kind' => 'reopened', 'key' => 'requeued', 'expected' => $fcr['requeued']]),
            'satisfaction' => $satisfaction,
            'satisfactionSeries' => collect($satisfaction['distribution'])->map(fn(int $count, int $score): array => [
                'x' => (string) $score,
                'y' => $count,
                'url' => $drill(['kind' => 'satisfaction_score', 'key' => $score, 'expected' => $count]),
            ])->values()->all(),
            'changeOutcomes' => $metrics->changeOutcomes($from, $to),
            'problemBacklog' => $metrics->problemBacklog(),
            'catalogDemand' => $metrics->catalogDemand($from, $to),
            // MVP-338: „Probleme trotz Wissensartikel" — Drilldown je
            // Artikel signiert (Sqid als Key, expected = Vorkommen).
            'recurring' => array_map(fn(array $row): array => [
                ...$row,
                'url' => $drill(['kind' => 'knowledge_recurrence', 'key' => $row['article']->sqid, 'expected' => $row['count']]),
            ], $metrics->recurringDespiteArticle($from, $to)),
        ]);
    }
}
