<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CohortComparisonBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Models\{Qualification, TimeEntry, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Feature 002 (Kohortenvergleich vor/nach Fortbildung): bildet für eine
 * gewählte Qualifikation die Kohorte der Mitarbeitenden, die sie erworben
 * haben, und vergleicht eine Kennzahl im Fenster VOR vs. NACH dem
 * Erwerbsdatum — je Mitarbeitendem und aggregiert.
 *
 * Datenquelle Fortbildungsdatum: `user_qualifications.valid_from` (Erwerbs-/
 * Gültig-ab-Datum aus der Qualifikationszuordnung). Trägt ein Mitarbeitender
 * KEIN valid_from, kann der Schnitt nicht gebildet werden — diese Personen
 * werden transparent als „ohne Erwerbsdatum" ausgewiesen, nicht stillschweigend
 * weggelassen.
 *
 * Kennzahl-Berechnung NICHT dupliziert: dieselben TimeEntry-Felder
 * (minutes/billable/rate) wie {@see EconomicsReportBuilder::timeAggregate()}
 * werden je Mitarbeitendem über ein gleichlanges Fenster aggregiert.
 */
class CohortComparisonBuilder {
    /**
     * @param  list<int>  $onlyUserIds  Kohorte auf diese User beschränken (Team-Filter); leer = alle
     * @return array{
     *   qualificationId:int,
     *   metric:string,
     *   windowDays:int,
     *   members: list<array{
     *     userId:int, userName:string, acquiredOn:string|null,
     *     before: float|null, after: float|null, delta: float|null,
     *     beforeMinutes:int, afterMinutes:int, improved: bool|null
     *   }>,
     *   aggregate: array{
     *     before: float|null, after: float|null, delta: float|null,
     *     membersWithDate:int, membersWithoutDate:int, improvedCount:int
     *   },
     *   weekly: list<array{week:int, value: float, minutes:int}>
     * }
     */
    public function build(Qualification $qualification, string $metric, int $windowDays = 90, array $onlyUserIds = []): array {
        $windowDays = max(7, $windowDays);

        /** @var Collection<int, User> $users */
        $users = $qualification->users()
            ->when($onlyUserIds !== [], fn($q) => $q->whereIn('users.id', $onlyUserIds))
            ->orderBy('name')->get(['users.id', 'users.name']);

        // Erwerbsdaten je User sammeln und daraus das globale Datumsfenster
        // aufspannen, um alle Zeitbuchungen der Kohorte in EINER Query zu laden
        // (vormals zwei Queries je Mitarbeitendem → N+1).
        $acquiredByUser = [];
        foreach ($users as $user) {
            $pivot = $user->getRelationValue('pivot');
            $raw = $pivot !== null ? $pivot->getAttribute('valid_from') : null;
            if ($raw !== null) {
                $acquiredByUser[(int) $user->id] = CarbonImmutable::parse((string) $raw);
            }
        }

        /** @var Collection<int, Collection<int, TimeEntry>> $entriesByUser */
        $entriesByUser = collect();
        if ($acquiredByUser !== []) {
            $minDate = null;
            $maxDate = null;
            foreach ($acquiredByUser as $on) {
                $lo = $on->subDays($windowDays);
                $hi = $on->addDays($windowDays - 1);
                $minDate = ($minDate === null || $lo->lt($minDate)) ? $lo : $minDate;
                $maxDate = ($maxDate === null || $hi->gt($maxDate)) ? $hi : $maxDate;
            }

            $entriesByUser = TimeEntry::query()
                ->whereIn('user_id', array_keys($acquiredByUser))
                ->whereBetween('date', [$minDate->toDateString(), $maxDate->toDateString()])
                ->get(['user_id', 'date', 'minutes', 'billable'])
                ->groupBy(static fn (TimeEntry $e): int => (int) $e->user_id);
        }

        $members = [];
        $beforeSum = 0.0;
        $afterSum = 0.0;
        $withDate = 0;
        $withoutDate = 0;
        $improved = 0;
        /** @var array<int, array{total:int, billable:int}> $weekTotals */
        $weekTotals = [];

        foreach ($users as $user) {
            $acquiredOn = $acquiredByUser[(int) $user->id] ?? null;

            if ($acquiredOn === null) {
                $withoutDate++;
                $members[] = [
                    'userId' => (int) $user->id,
                    'userName' => (string) $user->name,
                    'acquiredOn' => null,
                    'before' => null,
                    'after' => null,
                    'delta' => null,
                    'beforeMinutes' => 0,
                    'afterMinutes' => 0,
                    'improved' => null,
                ];

                continue;
            }

            $userEntries = $entriesByUser->get((int) $user->id, collect());
            $beforeWindow = $this->aggregate($userEntries, $acquiredOn->subDays($windowDays), $acquiredOn->subDay(), $metric);
            $afterWindow = $this->aggregate($userEntries, $acquiredOn, $acquiredOn->addDays($windowDays - 1), $metric);

            // Kohortenverlauf: Buchungen in Wochen RELATIV zum Erwerbsdatum
            // bucketen (Woche 0 = Erwerbswoche, negative Wochen = davor) und
            // über alle Mitglieder summieren — Zeitreihe für das Linien-Chart.
            foreach ($userEntries as $entry) {
                $offset = (int) floor($acquiredOn->diffInDays(CarbonImmutable::parse((string) $entry->date), false));
                if ($offset < -$windowDays || $offset > $windowDays - 1) {
                    continue; // außerhalb des Vergleichsfensters
                }
                $week = (int) floor($offset / 7);
                $weekTotals[$week] ??= ['total' => 0, 'billable' => 0];
                $weekTotals[$week]['total'] += (int) $entry->minutes;
                if ($entry->billable) {
                    $weekTotals[$week]['billable'] += (int) $entry->minutes;
                }
            }

            $before = $beforeWindow['value'];
            $after = $afterWindow['value'];
            $delta = ($before !== null && $after !== null) ? round($after - $before, 2) : null;
            $improvedFlag = $delta === null ? null : $this->isImprovement($metric, $delta);

            if ($before !== null && $after !== null) {
                $withDate++;
                $beforeSum += $before;
                $afterSum += $after;
                if ($improvedFlag === true) {
                    $improved++;
                }
            } else {
                $withoutDate++;
            }

            $members[] = [
                'userId' => (int) $user->id,
                'userName' => (string) $user->name,
                'acquiredOn' => $acquiredOn->toDateString(),
                'before' => $before,
                'after' => $after,
                'delta' => $delta,
                'beforeMinutes' => $beforeWindow['minutes'],
                'afterMinutes' => $afterWindow['minutes'],
                'improved' => $improvedFlag,
            ];
        }

        $aggBefore = $withDate > 0 ? round($beforeSum / $withDate, 2) : null;
        $aggAfter = $withDate > 0 ? round($afterSum / $withDate, 2) : null;
        $aggDelta = ($aggBefore !== null && $aggAfter !== null) ? round($aggAfter - $aggBefore, 2) : null;

        ksort($weekTotals);
        $weekly = [];
        foreach ($weekTotals as $week => $sums) {
            if ($sums['total'] === 0) {
                continue;
            }
            $weekly[] = [
                'week' => $week,
                'value' => $this->metricValue($metric, $sums['billable'], $sums['total']),
                'minutes' => $sums['total'],
            ];
        }

        return [
            'qualificationId' => (int) $qualification->id,
            'metric' => $metric,
            'windowDays' => $windowDays,
            'members' => $members,
            'aggregate' => [
                'before' => $aggBefore,
                'after' => $aggAfter,
                'delta' => $aggDelta,
                'membersWithDate' => $withDate,
                'membersWithoutDate' => $withoutDate,
                'improvedCount' => $improved,
            ],
            'weekly' => $weekly,
        ];
    }

    /**
     * Berechnet die Kennzahl für einen Mitarbeitenden in einem Datumsfenster
     * aus denselben TimeEntry-Feldern wie der Wirtschaftlichkeits-Report.
     * Filtert die bereits geladene Buchungsmenge in PHP nach Fenster, statt
     * je Fenster erneut die DB abzufragen.
     *
     * @param  Collection<int, TimeEntry>  $entries
     * @return array{value: float|null, minutes: int}
     */
    private function aggregate(Collection $entries, CarbonImmutable $from, CarbonImmutable $to, string $metric): array {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $total = 0;
        $billable = 0;
        foreach ($entries as $e) {
            $dateStr = CarbonImmutable::parse((string) $e->date)->toDateString();
            if ($dateStr < $fromStr || $dateStr > $toStr) {
                continue; // außerhalb des Fensters
            }
            $m = (int) $e->minutes;
            $total += $m;
            if ($e->billable) {
                $billable += $m;
            }
        }

        if ($total === 0) {
            return ['value' => null, 'minutes' => 0];
        }

        return ['value' => $this->metricValue($metric, $billable, $total), 'minutes' => $total];
    }

    /** Kennzahl aus Minuten-Summen (billableRate = abrechenbarer Anteil, reworkShare = Rest). */
    private function metricValue(string $metric, int $billableMinutes, int $totalMinutes): float {
        return match ($metric) {
            'reworkShare' => round((($totalMinutes - $billableMinutes) / $totalMinutes) * 100, 2),
            default => round(($billableMinutes / $totalMinutes) * 100, 2), // billableRate
        };
    }

    /** Verbesserung je nach Kennzahl-Richtung (billableRate ↑, reworkShare ↓). */
    private function isImprovement(string $metric, float $delta): bool {
        return $metric === 'reworkShare' ? $delta < 0 : $delta > 0;
    }

    /**
     * Unterstützte Kohorten-Kennzahlen (value => label-Key).
     *
     * @return array<string, string>
     */
    public static function metricOptions(): array {
        return [
            'billableRate' => 'reporting.cohort.metric.billableRate',
            'reworkShare' => 'reporting.cohort.metric.reworkShare',
        ];
    }
}
