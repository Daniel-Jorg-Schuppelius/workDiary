<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerRetentionReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Models\{Customer, DiaryEntry, Project, TimeEntry};
use Carbon\CarbonImmutable;

/**
 * Kundenbindung & Kohorten (MVP-466, Feature 002). Kohortenbasis ist das
 * Erstleistungsjahr je Kunde (MIN über TimeEntry.date via Projekt und
 * DiaryEntry.created_at, org-weit und ungefiltert).
 *
 * Die Bestandsbrücke geht bewusst exakt auf: Ende = Start + Neu (und am Ende
 * aktiv) + Zurückgewonnen − Neu-wieder-inaktiv − Verloren. „Aktiv zum
 * Zeitpunkt D" heißt: Leistung im Fenster (D − $lostAfterDays, D].
 */
class CustomerRetentionReportBuilder {
    /**
     * @param  list<int>  $excludedCustomerIds
     * @return array{
     *   cohorts: array{years: list<int>, rows: list<array{year:int, size:int, cells: list<?float>}>},
     *   bridge: array{start:int, end:int, new: list<array{customerId:int, customerName:string}>,
     *     reactivated: list<array{customerId:int, customerName:string}>,
     *     newChurned: list<array{customerId:int, customerName:string}>,
     *     lost: list<array{customerId:int, customerName:string}>},
     *   kpis: array{returningRate:?float, avgCustomerAgeYears:?float, newCount:int, lostCount:int, endActive:int},
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, int $cohortYears = 6, int $lostAfterDays = 365, array $excludedCustomerIds = []): array {
        $customers = Customer::query()
            ->when($excludedCustomerIds !== [], fn($q) => $q->whereNotIn('id', $excludedCustomerIds))
            ->orderBy('name')
            ->get(['id', 'name']);
        $names = $customers->mapWithKeys(static fn(Customer $c): array => [(int) $c->id => (string) $c->name])->all();

        $stats = $this->activityStats($from, $to, $lostAfterDays, array_keys($names));

        return [
            'cohorts' => $this->cohorts($stats, $to, $cohortYears),
            'bridge' => $this->bridge($stats, $names, $from),
            'kpis' => $this->kpis($stats, $to),
        ];
    }

    /**
     * Drilldown (MVP-470): Kunden einer Kohorte (Erstleistungsjahr) mit
     * Erst-/Letztleistung und — falls $activityYear gesetzt — der Angabe,
     * ob im Zieljahr Leistung bezogen wurde.
     *
     * @param  list<int>  $excludedCustomerIds
     * @return list<array{customerId:int, customerName:string, firstActivity:string, lastActivity:string, activeInYear:?bool}>
     */
    public function cohortCustomers(CarbonImmutable $from, CarbonImmutable $to, int $cohortYear, ?int $activityYear = null, int $lostAfterDays = 365, array $excludedCustomerIds = []): array {
        $customers = Customer::query()
            ->when($excludedCustomerIds !== [], fn($q) => $q->whereNotIn('id', $excludedCustomerIds))
            ->orderBy('name')
            ->get(['id', 'name']);
        $names = $customers->mapWithKeys(static fn(Customer $c): array => [(int) $c->id => (string) $c->name])->all();

        $stats = $this->activityStats($from, $to, $lostAfterDays, array_keys($names));

        $rows = [];
        foreach ($stats as $cid => $s) {
            if ($s['first'] === null || (int) substr($s['first'], 0, 4) !== $cohortYear) {
                continue;
            }
            $rows[] = [
                'customerId' => (int) $cid,
                'customerName' => $names[$cid] ?? ('#' . $cid),
                'firstActivity' => (string) $s['first'],
                'lastActivity' => (string) $s['last'],
                'activeInYear' => $activityYear !== null ? isset($s['years'][$activityYear]) : null,
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcasecmp($a['customerName'], $b['customerName']));

        return $rows;
    }

    /**
     * Aktivitätsstatistik je Kunde in einem Durchgang: Erst-/Letztleistung,
     * Leistungsjahre und Aktivität in den beiden Bilanzfenstern.
     *
     * @param  list<int>  $customerIds
     * @return array<int, array{first:?string, last:?string, years: array<int, true>, activeAtStart:bool, activeAtEnd:bool}>
     */
    private function activityStats(CarbonImmutable $from, CarbonImmutable $to, int $lostAfterDays, array $customerIds): array {
        $startWindowFrom = $from->subDays($lostAfterDays)->toDateString();
        $startWindowTo = $from->toDateString();
        $endWindowFrom = $to->subDays($lostAfterDays)->toDateString();
        $endWindowTo = $to->toDateString();

        /** @var array<int, array{first:?string, last:?string, years: array<int, true>, activeAtStart:bool, activeAtEnd:bool}> $stats */
        $stats = [];
        $init = static fn(): array => ['first' => null, 'last' => null, 'years' => [], 'activeAtStart' => false, 'activeAtEnd' => false];
        $record = function (int $cid, string $date) use (&$stats, $init, $startWindowFrom, $startWindowTo, $endWindowFrom, $endWindowTo): void {
            $stats[$cid] ??= $init();
            $s = &$stats[$cid];
            if ($s['first'] === null || $date < $s['first']) {
                $s['first'] = $date;
            }
            if ($s['last'] === null || $date > $s['last']) {
                $s['last'] = $date;
            }
            $s['years'][(int) substr($date, 0, 4)] = true;
            if ($date >= $startWindowFrom && $date < $startWindowTo) {
                $s['activeAtStart'] = true;
            }
            if ($date > $endWindowFrom && $date <= $endWindowTo) {
                $s['activeAtEnd'] = true;
            }
        };

        if ($customerIds !== []) {
            $projectToCustomer = Project::query()
                ->whereIn('customer_id', $customerIds)
                ->get(['id', 'customer_id'])
                ->mapWithKeys(static fn(Project $p): array => [(int) $p->id => (int) $p->customer_id])
                ->all();

            if ($projectToCustomer !== []) {
                TimeEntry::query()
                    ->whereIn('project_id', array_keys($projectToCustomer))
                    ->get(['project_id', 'date'])
                    ->each(function (TimeEntry $e) use ($record, $projectToCustomer): void {
                        $cid = $projectToCustomer[(int) $e->project_id] ?? null;
                        if ($cid !== null) {
                            $record($cid, $e->date instanceof \Carbon\CarbonInterface ? $e->date->toDateString() : substr((string) $e->date, 0, 10));
                        }
                    });
            }

            DiaryEntry::query()
                ->whereNotNull('customer_id')
                ->whereIn('customer_id', $customerIds)
                ->get(['customer_id', 'created_at'])
                ->each(function (DiaryEntry $e) use ($record): void {
                    if ($e->created_at !== null) {
                        $record((int) $e->customer_id, $e->created_at->toDateString());
                    }
                });
        }

        return $stats;
    }

    /**
     * Kohorten-Matrix: Erstleistungsjahr × Folgejahr-Offset, Zellwert =
     * Anteil der Kohorte mit Leistung im jeweiligen Jahr (Prozent).
     *
     * @param  array<int, array{first:?string, last:?string, years: array<int, true>, activeAtStart:bool, activeAtEnd:bool}>  $stats
     * @return array{years: list<int>, rows: list<array{year:int, size:int, cells: list<?float>}>}
     */
    private function cohorts(array $stats, CarbonImmutable $to, int $cohortYears): array {
        $endYear = (int) $to->format('Y');
        $firstCohortYear = $endYear - ($cohortYears - 1);
        $years = range($firstCohortYear, $endYear);

        $rows = [];
        foreach ($years as $cohortYear) {
            $members = array_filter($stats, static fn(array $s): bool => $s['first'] !== null && (int) substr($s['first'], 0, 4) === $cohortYear);
            $size = count($members);
            $cells = [];
            foreach (range(0, $endYear - $firstCohortYear) as $offset) {
                $targetYear = $cohortYear + $offset;
                if ($targetYear > $endYear || $size === 0) {
                    $cells[] = null;
                    continue;
                }
                $active = count(array_filter($members, static fn(array $s): bool => isset($s['years'][$targetYear])));
                $cells[] = round($active / $size * 100, 1);
            }
            $rows[] = ['year' => $cohortYear, 'size' => $size, 'cells' => $cells];
        }

        return ['years' => $years, 'rows' => $rows];
    }

    /**
     * @param  array<int, array{first:?string, last:?string, years: array<int, true>, activeAtStart:bool, activeAtEnd:bool}>  $stats
     * @param  array<int, string>  $names
     * @return array{start:int, end:int, new: list<array{customerId:int, customerName:string}>, reactivated: list<array{customerId:int, customerName:string}>, newChurned: list<array{customerId:int, customerName:string}>, lost: list<array{customerId:int, customerName:string}>}
     */
    private function bridge(array $stats, array $names, CarbonImmutable $from): array {
        $fromDate = $from->toDateString();
        $entry = static fn(int $cid): array => ['customerId' => $cid, 'customerName' => $names[$cid] ?? ('#' . $cid)];

        $start = 0;
        $new = [];
        $reactivated = [];
        $newChurned = [];
        $lost = [];
        $end = 0;

        foreach ($stats as $cid => $s) {
            $isNew = $s['first'] !== null && $s['first'] >= $fromDate;
            if ($s['activeAtStart']) {
                $start++;
            }
            if ($s['activeAtEnd']) {
                $end++;
            }

            if ($isNew) {
                if ($s['activeAtEnd']) {
                    $new[] = $entry($cid);
                } else {
                    $newChurned[] = $entry($cid);
                }
                continue;
            }

            if ($s['activeAtStart'] && ! $s['activeAtEnd']) {
                $lost[] = $entry($cid);
            } elseif (! $s['activeAtStart'] && $s['activeAtEnd']) {
                $reactivated[] = $entry($cid);
            }
        }

        return [
            'start' => $start,
            'end' => $end,
            'new' => $new,
            'reactivated' => $reactivated,
            'newChurned' => $newChurned,
            'lost' => $lost,
        ];
    }

    /**
     * @param  array<int, array{first:?string, last:?string, years: array<int, true>, activeAtStart:bool, activeAtEnd:bool}>  $stats
     * @return array{returningRate:?float, avgCustomerAgeYears:?float, newCount:int, lostCount:int, endActive:int}
     */
    private function kpis(array $stats, CarbonImmutable $to): array {
        $year = (int) $to->format('Y');
        $prevActive = array_filter($stats, static fn(array $s): bool => isset($s['years'][$year - 1]));
        $returning = array_filter($prevActive, static fn(array $s): bool => isset($s['years'][$year]));
        $endActive = array_filter($stats, static fn(array $s): bool => $s['activeAtEnd']);

        $ages = array_map(
            static fn(array $s): float => max(0.0, CarbonImmutable::parse((string) $s['first'])->diffInDays($to) / 365.25),
            array_filter($endActive, static fn(array $s): bool => $s['first'] !== null),
        );

        return [
            'returningRate' => $prevActive !== [] ? round(count($returning) / count($prevActive) * 100, 1) : null,
            'avgCustomerAgeYears' => $ages !== [] ? round(array_sum($ages) / count($ages), 1) : null,
            'newCount' => count(array_filter($stats, static fn(array $s): bool => $s['first'] !== null && isset($s['years'][$year]) && (int) substr((string) $s['first'], 0, 4) === $year)),
            'lostCount' => count(array_filter($stats, static fn(array $s): bool => $s['activeAtStart'] && ! $s['activeAtEnd'])),
            'endActive' => count($endActive),
        ];
    }
}
