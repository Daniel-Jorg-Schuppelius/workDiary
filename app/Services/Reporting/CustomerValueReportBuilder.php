<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerValueReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Models\{Customer, DiaryEntry, Invoice, Project, TimeEntry};
use App\Support\ChartBucket;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Kundenwert & Portfolio (MVP-465, Feature 002): RFM-Segmentierung
 * (Recency/Frequency/Monetary als Quintil-Scores), Umsatzkonzentration
 * (Top-N-Anteil, Herfindahl-Hirschman-Index) und Risikoliste gefährdeter
 * A-Kunden.
 *
 * Erlös = abrechenbare TimeEntry.rate-Snapshots — dieselbe Quelle wie die
 * Wirtschaftlichkeitssicht ({@see EconomicsReportBuilder}), keine parallele
 * Berechnung. Fakturierter Umsatz (Invoice) ist bewusst nur Zweitwert:
 * bei externer Rechnungshoheit unvollständig.
 *
 * Erst-/Letztleistung je Kunde werden org-weit und UNGEFILTERT bestimmt
 * (Kundenfakten, kein Zeitraumausschnitt) — nur die Zeitraum-Kennzahlen
 * folgen den Projekt-/Nutzerfiltern.
 */
class CustomerValueReportBuilder {
    /** HHI-Ampelschwellen (Marktkonzentrations-Konvention). */
    public const HHI_MODERATE = 1500;

    public const HHI_HIGH = 2500;

    public function __construct(private readonly LexofficeRevenueMirror $externalRevenue) {}

    /**
     * @param  list<int>  $excludedCustomerIds
     * @return array{
     *   rows: list<array{customerId:int, customerName:string, recencyDays:?int, frequencyDays:int,
     *     revenue:float, invoiced:float, totalMinutes:int, r:?int, f:?int, m:?int, segment:string,
     *     firstActivity:?string, lastActivity:?string}>,
     *   segments: array<string, int>,
     *   concentration: array{totalRevenue:float, top5Share:?float, top10Share:?float, hhi:?int, activeCustomers:int},
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, ?int $projectId, ?int $userId, array $excludedCustomerIds = []): array {
        $customers = Customer::query()
            ->when($excludedCustomerIds !== [], fn($q) => $q->whereNotIn('id', $excludedCustomerIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Projekt → Kunde (org-weit für Erst-/Letztleistung, gefiltert für den Zeitraum).
        $allProjects = Project::query()->whereNotNull('customer_id')->get(['id', 'customer_id']);
        $projectToCustomer = $allProjects->mapWithKeys(static fn(Project $p): array => [(int) $p->id => (int) $p->customer_id])->all();
        $filteredProjectIds = $projectId !== null
            ? (isset($projectToCustomer[$projectId]) ? [$projectId] : [])
            : array_keys($projectToCustomer);

        // Zeitraum-Kennzahlen aus TimeEntry (Erlös/Minuten/Aktivitätstage) …
        $activityDays = [];
        $revenue = [];
        $minutes = [];
        if ($filteredProjectIds !== []) {
            TimeEntry::query()
                ->whereBetween('date', DateRange::days($from, $to))
                ->whereIn('project_id', $filteredProjectIds)
                ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
                ->get(['project_id', 'date', 'minutes', 'billable', 'rate'])
                ->each(function (TimeEntry $e) use (&$activityDays, &$revenue, &$minutes, $projectToCustomer): void {
                    $cid = $projectToCustomer[(int) $e->project_id] ?? null;
                    if ($cid === null) {
                        return;
                    }
                    $activityDays[$cid][$e->date instanceof \Carbon\CarbonInterface ? $e->date->toDateString() : (string) $e->date] = true;
                    $minutes[$cid] = ($minutes[$cid] ?? 0) + (int) $e->minutes;
                    if ($e->billable) {
                        $revenue[$cid] = ($revenue[$cid] ?? 0.0) + ($e->rate?->toFloat() ?? 0.0);
                    }
                });
        }

        // … plus Auftragstage aus DiaryEntry (Kunden ohne Zeitbuchung bleiben sichtbar).
        DiaryEntry::query()
            ->whereNotNull('customer_id')
            ->whereBetween('created_at', [$from, $to])
            ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
            ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
            ->get(['customer_id', 'created_at'])
            ->each(function (DiaryEntry $e) use (&$activityDays): void {
                $activityDays[(int) $e->customer_id][$e->created_at?->toDateString() ?? ''] = true;
            });

        [$firstActivity, $lastActivity] = $this->activityBounds($projectToCustomer);

        $invoiced = $this->invoicedPerCustomer($from, $to);

        // RFM-Quintile über die im Zeitraum aktiven Kunden.
        $active = $customers->filter(fn(Customer $c): bool => ($activityDays[(int) $c->id] ?? []) !== []);
        $recencyByCustomer = [];
        foreach ($customers as $c) {
            $cid = (int) $c->id;
            $last = $lastActivity[$cid] ?? null;
            $recencyByCustomer[$cid] = $last !== null ? (int) max(0, CarbonImmutable::parse($last)->diffInDays($to, false)) : null;
        }
        $rScores = $this->quintileScores(
            $active->mapWithKeys(fn(Customer $c): array => [(int) $c->id => (float) ($recencyByCustomer[(int) $c->id] ?? 0)])->all(),
            higherIsBetter: false,
        );
        $fScores = $this->quintileScores(
            $active->mapWithKeys(fn(Customer $c): array => [(int) $c->id => (float) count($activityDays[(int) $c->id] ?? [])])->all(),
            higherIsBetter: true,
        );
        $mScores = $this->quintileScores(
            $active->mapWithKeys(fn(Customer $c): array => [(int) $c->id => (float) ($revenue[(int) $c->id] ?? 0.0)])->all(),
            higherIsBetter: true,
        );

        $rows = [];
        $segments = ['champion' => 0, 'loyal' => 0, 'potential' => 0, 'new' => 0, 'at_risk' => 0, 'inactive' => 0];
        foreach ($customers as $c) {
            $cid = (int) $c->id;
            $freq = count($activityDays[$cid] ?? []);
            $rev = round($revenue[$cid] ?? 0.0, 2);
            $r = $rScores[$cid] ?? null;
            $f = $fScores[$cid] ?? null;
            $m = $mScores[$cid] ?? null;
            $segment = $this->segment($freq, $r, $f, $m, $firstActivity[$cid] ?? null, $from);
            $segments[$segment]++;

            $rows[] = [
                'customerId' => $cid,
                'customerName' => $c->name,
                'recencyDays' => $recencyByCustomer[$cid],
                'frequencyDays' => $freq,
                'revenue' => $rev,
                'invoiced' => round($invoiced[$cid] ?? 0.0, 2),
                'totalMinutes' => (int) ($minutes[$cid] ?? 0),
                'r' => $r,
                'f' => $f,
                'm' => $m,
                'segment' => $segment,
                'firstActivity' => $firstActivity[$cid] ?? null,
                'lastActivity' => $lastActivity[$cid] ?? null,
            ];
        }

        return [
            'rows' => $rows,
            'segments' => $segments,
            'concentration' => $this->concentration($rows),
        ];
    }

    /**
     * Gefährdete A-Kunden: hoher Zeitraum-Erlös (M ≥ 4), aber seit
     * $riskDays Tagen ohne Leistung — sortiert nach Erlös.
     *
     * @param  list<array{customerId:int, customerName:string, recencyDays:?int, frequencyDays:int, revenue:float, invoiced:float, totalMinutes:int, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
     * @return list<array{customerId:int, customerName:string, recencyDays:?int, frequencyDays:int, revenue:float, invoiced:float, totalMinutes:int, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>
     */
    public function riskRows(array $rows, int $riskDays = 60, int $limit = 10): array {
        return array_slice(array_values(array_filter($rows, static fn(array $row): bool => ($row['m'] ?? 0) >= 4
            && $row['recencyDays'] !== null
            && $row['recencyDays'] >= $riskDays)), 0, $limit);
    }

    /**
     * Erlös (abrechenbare rate-Snapshots) je Kunde im Zeitraum, in der
     * Header-Granularität — Sparkline-Reihe der Risikoliste.
     *
     * @param  list<int>  $customerIds
     * @return array<int, list<float>> customerId → Werte je Bucket (alt → neu)
     */
    public function monthlyRevenueSeries(array $customerIds, CarbonImmutable $from, CarbonImmutable $to, string $unit): array {
        if ($customerIds === []) {
            return [];
        }

        $granularity = ChartBucket::granularity($unit, $from, $to);
        if ($granularity === 'hour') {
            $granularity = 'day';
        }
        /** @var list<string> $bucketKeys */
        $bucketKeys = [];
        for ($cursor = $from->startOfDay(); $cursor->lte($to); $cursor = $cursor->addDay()) {
            $key = ChartBucket::keyLabel($granularity, $cursor)[0];
            if (! in_array($key, $bucketKeys, true)) {
                $bucketKeys[] = $key;
            }
        }

        $projectToCustomer = Project::query()
            ->whereIn('customer_id', $customerIds)
            ->get(['id', 'customer_id'])
            ->mapWithKeys(static fn(Project $p): array => [(int) $p->id => (int) $p->customer_id])
            ->all();

        $byCustomer = array_fill_keys($customerIds, array_fill_keys($bucketKeys, 0.0));
        if ($projectToCustomer !== []) {
            TimeEntry::query()
                ->whereBetween('date', DateRange::days($from, $to))
                ->whereIn('project_id', array_keys($projectToCustomer))
                ->where('billable', true)
                ->get(['project_id', 'date', 'rate'])
                ->each(function (TimeEntry $e) use (&$byCustomer, $projectToCustomer, $granularity): void {
                    $cid = $projectToCustomer[(int) $e->project_id] ?? null;
                    if ($cid === null) {
                        return;
                    }
                    $day = $e->date instanceof \Carbon\CarbonInterface
                        ? CarbonImmutable::parse($e->date->toDateString())
                        : CarbonImmutable::parse((string) $e->date);
                    $key = ChartBucket::keyLabel($granularity, $day)[0];
                    if (isset($byCustomer[$cid][$key])) {
                        $byCustomer[$cid][$key] += ($e->rate?->toFloat() ?? 0.0);
                    }
                });
        }

        return array_map(static fn(array $series): array => array_map(static fn(float $v): float => round($v, 2), array_values($series)), $byCustomer);
    }

    /**
     * Erst-/Letztleistung je Kunde (org-weit, ungefiltert): MIN/MAX über
     * TimeEntry.date (via Projekt) und DiaryEntry.created_at.
     *
     * @param  array<int, int>  $projectToCustomer
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function activityBounds(array $projectToCustomer): array {
        $first = [];
        $last = [];

        if ($projectToCustomer !== []) {
            TimeEntry::query()
                ->whereIn('project_id', array_keys($projectToCustomer))
                ->groupBy('project_id')
                ->selectRaw('project_id, MIN(date) AS first_date, MAX(date) AS last_date')
                ->get()
                ->each(function ($row) use (&$first, &$last, $projectToCustomer): void {
                    $cid = $projectToCustomer[(int) $row->getAttribute('project_id')] ?? null;
                    if ($cid === null) {
                        return;
                    }
                    $firstDate = substr((string) $row->getAttribute('first_date'), 0, 10);
                    $lastDate = substr((string) $row->getAttribute('last_date'), 0, 10);
                    if (! isset($first[$cid]) || $firstDate < $first[$cid]) {
                        $first[$cid] = $firstDate;
                    }
                    if (! isset($last[$cid]) || $lastDate > $last[$cid]) {
                        $last[$cid] = $lastDate;
                    }
                });
        }

        DiaryEntry::query()
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, MIN(created_at) AS first_at, MAX(created_at) AS last_at')
            ->get()
            ->each(function ($row) use (&$first, &$last): void {
                $cid = (int) $row->getAttribute('customer_id');
                $firstDate = substr((string) $row->getAttribute('first_at'), 0, 10);
                $lastDate = substr((string) $row->getAttribute('last_at'), 0, 10);
                if (! isset($first[$cid]) || $firstDate < $first[$cid]) {
                    $first[$cid] = $firstDate;
                }
                if (! isset($last[$cid]) || $lastDate > $last[$cid]) {
                    $last[$cid] = $lastDate;
                }
            });

        return [$first, $last];
    }

    /**
     * Fakturierter Zweitwert je Kunde (vereinfachte Sicht): ausgestellte/
     * (teil)bezahlte lokale Rechnungen der Typen invoice/partial/final plus
     * gespiegelte Lexoffice-Belege (Phase-54-Nachtrag) — bei externer
     * Rechnungshoheit kämen sonst keine fakturierten Beträge zusammen.
     *
     * @return array<int, float>
     */
    private function invoicedPerCustomer(CarbonImmutable $from, CarbonImmutable $to): array {
        $sums = [];
        Invoice::query()
            ->whereBetween('issued_on', DateRange::days($from, $to))
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID])
            ->whereIn('type', [Invoice::TYPE_INVOICE, Invoice::TYPE_PARTIAL, Invoice::TYPE_FINAL])
            ->get(['customer_id', 'total'])
            ->each(function (Invoice $inv) use (&$sums): void {
                $sums[(int) $inv->customer_id] = ($sums[(int) $inv->customer_id] ?? 0.0) + ($inv->total?->toFloat() ?? 0.0);
            });

        foreach ($this->externalRevenue->perCustomer($from->toDateString(), $to->toDateString()) as $cid => $ext) {
            $sums[$cid] = ($sums[$cid] ?? 0.0) + $ext['total'];
        }

        return $sums;
    }

    /**
     * Quintil-Scores 1–5 über die Perzentil-Position des Werts; gleiche
     * Werte erhalten denselben Score (stabil bei Bindungen).
     *
     * @param  array<int, float>  $values  Schlüssel → Wert
     * @return array<int, int>
     */
    private function quintileScores(array $values, bool $higherIsBetter): array {
        $n = count($values);
        if ($n === 0) {
            return [];
        }

        $sorted = array_values($values);
        sort($sorted);
        $scores = [];
        foreach ($values as $key => $value) {
            $below = 0;
            foreach ($sorted as $v) {
                if ($v < $value) {
                    $below++;
                } else {
                    break;
                }
            }
            $score = min(5, (int) floor($below / $n * 5) + 1);
            $scores[$key] = $higherIsBetter ? $score : 6 - $score;
        }

        return $scores;
    }

    /** Sequenzielle Segment-Zuordnung (erste zutreffende Regel gewinnt). */
    private function segment(int $frequencyDays, ?int $r, ?int $f, ?int $m, ?string $firstActivity, CarbonImmutable $from): string {
        if ($frequencyDays === 0) {
            return 'inactive';
        }
        if ($firstActivity !== null && $firstActivity >= $from->toDateString()) {
            return 'new';
        }
        if (($r ?? 0) >= 4 && ($f ?? 0) >= 4 && ($m ?? 0) >= 4) {
            return 'champion';
        }
        if (($r ?? 0) <= 2 && ($m ?? 0) >= 4) {
            return 'at_risk';
        }
        if (($r ?? 0) <= 2) {
            return 'inactive';
        }
        if (($f ?? 0) >= 3) {
            return 'loyal';
        }

        return 'potential';
    }

    /**
     * @param  list<array{customerId:int, customerName:string, recencyDays:?int, frequencyDays:int, revenue:float, invoiced:float, totalMinutes:int, r:?int, f:?int, m:?int, segment:string, firstActivity:?string, lastActivity:?string}>  $rows
     * @return array{totalRevenue:float, top5Share:?float, top10Share:?float, hhi:?int, activeCustomers:int}
     */
    private function concentration(array $rows): array {
        $revenues = collect($rows)->pluck('revenue')->filter(static fn(float $v): bool => $v > 0)->sortDesc()->values();
        $total = (float) $revenues->sum();
        $share = fn(Collection $part): ?float => $total > 0 ? round((float) $part->sum() / $total * 100, 1) : null;

        $hhi = null;
        if ($total > 0) {
            $hhi = (int) round($revenues->reduce(static fn(float $carry, float $v): float => $carry + (($v / $total * 100) ** 2), 0.0));
        }

        return [
            'totalRevenue' => round($total, 2),
            'top5Share' => $share($revenues->take(5)),
            'top10Share' => $share($revenues->take(10)),
            'hhi' => $hhi,
            'activeCustomers' => collect($rows)->filter(static fn(array $row): bool => $row['frequencyDays'] > 0)->count(),
        ];
    }
}
