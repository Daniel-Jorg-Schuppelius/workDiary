<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Safety\{SafetyEventKind, SafetyEventSeverity, SafetyEventStatus};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\ResolvesStandardReportFilters;
use App\Models\SafetyEvent;
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Sicherheits-Auswertung (Feature 013): Ereignisse je Art und Schweregrad im
 * Zeitraum sowie offen vs. geschlossen.
 */
class SafetyReportController extends Controller {
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;

    public function index(Request $request): View {
        Gate::authorize('viewAny', SafetyEvent::class);

        [$fromDate, $toDate] = $this->resolveRange($request);
        // Mitarbeiter-/Team-Filter greift auf den Melder (reported_by_user_id).
        $filters = $this->standardFilters($request, ['user', 'team'], $fromDate, $toDate);

        $eventsQuery = SafetyEvent::query()
            ->whereBetween('occurred_at', [$fromDate, $toDate]);
        $filters->applyUserAndTeam($eventsQuery, 'reported_by_user_id');
        $events = $eventsQuery->get(['kind', 'severity', 'status', 'occurred_at']);

        $byKind = [];
        foreach (SafetyEventKind::cases() as $kind) {
            $byKind[$kind->value] = $events->where('kind', $kind)->count();
        }

        $bySeverity = [];
        foreach (SafetyEventSeverity::cases() as $severity) {
            $bySeverity[$severity->value] = $events->where('severity', $severity)->count();
        }

        $closed = $events->where('status', SafetyEventStatus::Closed)->count();
        $open = $events->count() - $closed;

        return view('reports.safety', [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'total' => $events->count(),
            'byKind' => $byKind,
            'bySeverity' => $bySeverity,
            'open' => $open,
            'closed' => $closed,
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team'],
            'monthlySeries' => $this->monthlySeries($events, $fromDate, $toDate),
            'statusMonthlySeries' => $this->statusMonthlySeries($events, $fromDate, $toDate),
            'statusBands' => $this->statusBands(),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($fromDate, $toDate)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($fromDate, $toDate)),
            ...$this->standardFilterOptions(['user', 'team'], $filters),
        ]);
    }

    /**
     * Ereignisse je Monat, Zweitserie = davon geschlossen.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, SafetyEvent>  $events
     * @return list<array{x: string, y: int, y2: int}>
     */
    private function monthlySeries($events, CarbonImmutable $from, CarbonImmutable $to): array {
        if ($events->isEmpty()) {
            return []; // Leerzustand statt Null-Serie (§Diagramm-UX).
        }

        $granularity = $this->bucketGranularity($from, $to);
        $bucketList = $this->buildBucketsInRange($from, $to);
        /** @var array<string, array{total: int, closed: int}> $byKey */
        $byKey = [];
        foreach ($bucketList as $bucket) {
            $byKey[$bucket['key']] = ['total' => 0, 'closed' => 0];
        }
        foreach ($events as $event) {
            $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $event->occurred_at))[0];
            if (! array_key_exists($key, $byKey)) {
                continue;
            }
            $byKey[$key]['total']++;
            if ($event->status === SafetyEventStatus::Closed) {
                $byKey[$key]['closed']++;
            }
        }

        $series = [];
        foreach ($bucketList as $bucket) {
            $series[] = [
                'x' => $bucket['shortLabel'],
                'y' => $byKey[$bucket['key']]['total'],
                'y2' => $byKey[$bucket['key']]['closed'],
            ];
        }

        return $series;
    }

    /**
     * Ereignisse je Monat, gestapelt nach Bearbeitungsstatus.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, SafetyEvent>  $events
     * @return list<array<string, string|int>>
     */
    private function statusMonthlySeries($events, CarbonImmutable $from, CarbonImmutable $to): array {
        if ($events->isEmpty()) {
            return []; // Leerzustand statt Null-Serie (§Diagramm-UX).
        }

        $statusValues = array_column(SafetyEventStatus::cases(), 'value');
        $granularity = $this->bucketGranularity($from, $to);
        $bucketList = $this->buildBucketsInRange($from, $to);
        /** @var array<string, array<string, int>> $byKey */
        $byKey = [];
        foreach ($bucketList as $bucket) {
            $byKey[$bucket['key']] = array_fill_keys($statusValues, 0);
        }
        foreach ($events as $event) {
            $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $event->occurred_at))[0];
            if (isset($byKey[$key][$event->status->value])) {
                $byKey[$key][$event->status->value]++;
            }
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
    private function statusBands(): array {
        return array_map(static fn(SafetyEventStatus $status): array => [
            'key' => $status->value,
            'label' => $status->label(),
        ], SafetyEventStatus::cases());
    }
}
