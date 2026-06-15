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
     *   }
     * }
     */
    public function build(Qualification $qualification, string $metric, int $windowDays = 90): array {
        $windowDays = max(7, $windowDays);

        /** @var Collection<int, User> $users */
        $users = $qualification->users()->orderBy('name')->get(['users.id', 'users.name']);

        $members = [];
        $beforeSum = 0.0;
        $afterSum = 0.0;
        $withDate = 0;
        $withoutDate = 0;
        $improved = 0;

        foreach ($users as $user) {
            $pivot = $user->getRelationValue('pivot');
            $acquiredRaw = $pivot !== null ? $pivot->getAttribute('valid_from') : null;
            $acquiredOn = $acquiredRaw !== null ? CarbonImmutable::parse((string) $acquiredRaw) : null;

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

            $beforeWindow = $this->aggregate((int) $user->id, $acquiredOn->subDays($windowDays), $acquiredOn->subDay(), $metric);
            $afterWindow = $this->aggregate((int) $user->id, $acquiredOn, $acquiredOn->addDays($windowDays - 1), $metric);

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
        ];
    }

    /**
     * Berechnet die Kennzahl für einen Mitarbeitenden in einem Datumsfenster
     * aus denselben TimeEntry-Feldern wie der Wirtschaftlichkeits-Report.
     *
     * @return array{value: float|null, minutes: int}
     */
    private function aggregate(int $userId, CarbonImmutable $from, CarbonImmutable $to, string $metric): array {
        /** @var Collection<int, TimeEntry> $entries */
        $entries = TimeEntry::query()
            ->where('user_id', $userId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['minutes', 'billable']);

        $total = 0;
        $billable = 0;
        $nonBillable = 0;
        foreach ($entries as $e) {
            $m = (int) $e->minutes;
            $total += $m;
            if ($e->billable) {
                $billable += $m;
            } else {
                $nonBillable += $m;
            }
        }

        if ($total === 0) {
            return ['value' => null, 'minutes' => 0];
        }

        $value = match ($metric) {
            'reworkShare' => round(($nonBillable / $total) * 100, 2),
            default => round(($billable / $total) * 100, 2), // billableRate
        };

        return ['value' => $value, 'minutes' => $total];
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
