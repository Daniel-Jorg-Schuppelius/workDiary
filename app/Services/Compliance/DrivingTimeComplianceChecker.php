<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DrivingTimeComplianceChecker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use Carbon\CarbonImmutable;

/**
 * Lenk-/Ruhezeit-Prüfung (Feature 144, MVP-719) auf den erfassten Fahrten
 * (TravelLog) EINES Fahrers — Muster {@see AttendanceComplianceChecker}:
 * BEWUSST PURE, keine DB; der {@see ComplianceScanService} lädt und filtert
 * (nur Fahrzeuge mit `subject_to_driving_time_rules`, Org-Schalter).
 *
 * Grenzwerte ausschließlich aus {@see DrivingTimeRules}. Befunde nutzen das
 * gemeinsame {@see AttendanceComplianceFinding} (Minuten), damit Recorder,
 * Historie und Report unverändert funktionieren; Kategorie `drivingTime`.
 *
 * Eingabe: chronologisch beliebige Fahrten in Wandzeit der Anzeige-Zeitzone
 *  list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>
 */
final class DrivingTimeComplianceChecker {
    public const CATEGORY = 'drivingTime';

    /** Art. 6 Abs. 1: Tageslenkzeit > 9 h (bzw. > 10 h / dritte Verlängerung). */
    public const KIND_DAILY_DRIVING = 'dailyDriving';

    /** Art. 6 Abs. 2: Wochenlenkzeit > 56 h. */
    public const KIND_WEEKLY_DRIVING = 'weeklyDriving';

    /** Art. 6 Abs. 3: Doppelwoche > 90 h. */
    public const KIND_FORTNIGHT_DRIVING = 'fortnightDriving';

    /** Art. 7: Lenkzeit > 4,5 h ohne gültige Fahrtunterbrechung. */
    public const KIND_BREAK_MISSING = 'drivingBreakMissing';

    /** Art. 8 Abs. 2/4: tägliche Ruhezeit < 9 h bzw. vierte Reduzierung der Woche. */
    public const KIND_DAILY_REST = 'dailyRest';

    /** Art. 8 Abs. 6: wöchentliche Ruhezeit < 24 h bzw. zweimal reduziert in Folge. */
    public const KIND_WEEKLY_REST = 'weeklyRest';

    /** Vorlauf in Tagen, den der Aufrufer vor `from` laden muss (Doppelwoche + Wochenruhezeit). */
    public const LOOKBACK_DAYS = 21;

    /** @return list<string> */
    public static function kinds(): array {
        return [
            self::KIND_DAILY_DRIVING,
            self::KIND_WEEKLY_DRIVING,
            self::KIND_FORTNIGHT_DRIVING,
            self::KIND_BREAK_MISSING,
            self::KIND_DAILY_REST,
            self::KIND_WEEKLY_REST,
        ];
    }

    /**
     * @param  list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>  $trips
     * @return list<AttendanceComplianceFinding>
     */
    public function checkUser(int $userId, array $trips): array {
        $trips = self::normalize($trips);
        if ($trips === []) {
            return [];
        }

        $days = self::aggregateDays($trips);
        $findings = [];

        foreach ($this->checkDailyDriving($userId, $days) as $f) {
            $findings[] = $f;
        }
        foreach ($this->checkWeeklyAndFortnightDriving($userId, $days) as $f) {
            $findings[] = $f;
        }
        foreach ($this->checkBreaks($userId, $trips) as $f) {
            $findings[] = $f;
        }
        foreach ($this->checkDailyRest($userId, $days) as $f) {
            $findings[] = $f;
        }
        foreach ($this->checkWeeklyRest($userId, $trips, $days) as $f) {
            $findings[] = $f;
        }

        usort($findings, static fn(AttendanceComplianceFinding $a, AttendanceComplianceFinding $b): int => [$a->date, $a->kind] <=> [$b->date, $b->kind]);

        return $findings;
    }

    // ── Einzelprüfungen ──────────────────────────────────────────────────

    /**
     * @param  array<string, array{minutes:int, first_start: CarbonImmutable, last_end: CarbonImmutable}>  $days
     * @return list<AttendanceComplianceFinding>
     */
    private function checkDailyDriving(int $userId, array $days): array {
        $findings = [];
        foreach (self::groupByWeek($days) as $week) {
            foreach (DrivingTimeRules::evaluateWeekDailyDriving($week['minutes_by_date']) as $v) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $v['date'],
                    kind: self::KIND_DAILY_DRIVING,
                    severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                    value: $v['value'],
                    threshold: $v['threshold'],
                );
            }
        }

        return $findings;
    }

    /**
     * Wochenlenkzeit (Art. 6 Abs. 2) je ISO-Woche und Doppelwoche (Art. 6
     * Abs. 3) als gleitendes Paar aufeinanderfolgender Wochen; Befunddatum
     * ist jeweils der Sonntag der (späteren) Woche.
     *
     * @param  array<string, array{minutes:int, first_start: CarbonImmutable, last_end: CarbonImmutable}>  $days
     * @return list<AttendanceComplianceFinding>
     */
    private function checkWeeklyAndFortnightDriving(int $userId, array $days): array {
        $weeks = self::groupByWeek($days);
        $findings = [];
        foreach ($weeks as $week) {
            $total = array_sum($week['minutes_by_date']);
            if ($total > DrivingTimeRules::WEEKLY_DRIVING_MINUTES) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $week['week_end'],
                    kind: self::KIND_WEEKLY_DRIVING,
                    severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                    value: $total,
                    threshold: DrivingTimeRules::WEEKLY_DRIVING_MINUTES,
                );
            }

            $previous = $weeks[$week['previous_key']] ?? null;
            $fortnight = $total + ($previous !== null ? array_sum($previous['minutes_by_date']) : 0);
            if ($fortnight > DrivingTimeRules::FORTNIGHT_DRIVING_MINUTES) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $week['week_end'],
                    kind: self::KIND_FORTNIGHT_DRIVING,
                    severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                    value: $fortnight,
                    threshold: DrivingTimeRules::FORTNIGHT_DRIVING_MINUTES,
                );
            }
        }

        return $findings;
    }

    /**
     * @param  list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>  $trips
     * @return list<AttendanceComplianceFinding>
     */
    private function checkBreaks(int $userId, array $trips): array {
        $findings = [];
        foreach (DrivingTimeRules::evaluateBreaks($trips)['violations'] as $v) {
            $findings[] = new AttendanceComplianceFinding(
                userId: $userId,
                date: $v['date'],
                kind: self::KIND_BREAK_MISSING,
                severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                value: $v['value'],
                threshold: DrivingTimeRules::BREAK_AFTER_DRIVING_MINUTES,
            );
        }

        return $findings;
    }

    /**
     * Tägliche Ruhezeit = Lücke zwischen letzter Fahrt eines Fahrtags und
     * erster Fahrt des nächsten Fahrtags; Lücken ≥ 24 h liegen außerhalb des
     * 24-h-Zeitraums (Art. 8 Abs. 2) und bleiben unbewertet. Unter 9 h ist
     * ein Verstoß; 9–11 h ist eine Reduzierung, ab der vierten je ISO-Woche
     * (Näherung für „zwischen zwei wöchentlichen Ruhezeiten", Art. 8 Abs. 4)
     * ebenfalls ein Verstoß.
     *
     * @param  array<string, array{minutes:int, first_start: CarbonImmutable, last_end: CarbonImmutable}>  $days
     * @return list<AttendanceComplianceFinding>
     */
    private function checkDailyRest(int $userId, array $days): array {
        ksort($days);
        $dates = array_keys($days);
        /** @var array<string, int> $reductionsByWeek */
        $reductionsByWeek = [];
        $findings = [];

        for ($i = 1, $n = count($dates); $i < $n; $i++) {
            $prevEnd = $days[$dates[$i - 1]]['last_end'];
            $currStart = $days[$dates[$i]]['first_start'];
            if ($currStart->lessThanOrEqualTo($prevEnd)) {
                continue;
            }
            $gap = (int) $prevEnd->diffInMinutes($currStart, false);
            if ($gap >= DrivingTimeRules::DAILY_WINDOW_MINUTES) {
                continue;
            }

            $class = DrivingTimeRules::classifyDailyRest($gap);
            if ($class === DrivingTimeRules::REST_REGULAR) {
                continue;
            }
            if ($class === DrivingTimeRules::REST_INSUFFICIENT) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $dates[$i],
                    kind: self::KIND_DAILY_REST,
                    severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                    value: $gap,
                    threshold: DrivingTimeRules::DAILY_REST_REDUCED_MINUTES,
                );

                continue;
            }

            $weekKey = CarbonImmutable::parse($dates[$i])->isoFormat('GGGG-[W]WW');
            $reductionsByWeek[$weekKey] = ($reductionsByWeek[$weekKey] ?? 0) + 1;
            if ($reductionsByWeek[$weekKey] > DrivingTimeRules::DAILY_REST_REDUCTIONS_PER_WEEK) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $dates[$i],
                    kind: self::KIND_DAILY_REST,
                    severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                    value: $gap,
                    threshold: DrivingTimeRules::DAILY_REST_MINUTES,
                );
            }
        }

        return $findings;
    }

    /**
     * Wöchentliche Ruhezeit (Art. 8 Abs. 6): längste Fahrpause, die eine
     * ISO-Woche berührt. Bewertet werden nur Wochen, deren Ränder durch
     * Fahrten VOR und NACH der Woche begrenzt sind — sonst wäre die Länge der
     * Randpause unbekannt (kein Falsch-Positiv am Datenrand). < 24 h ist ein
     * Verstoß; 24–45 h ist eine Reduzierung (Hinweis — der Ausgleich bis zum
     * Ende der dritten Folgewoche ist aus Fahrten nicht nachweisbar); zwei
     * reduzierte Wochen in Folge sind ein Verstoß.
     *
     * @param  list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>  $trips
     * @param  array<string, array{minutes:int, first_start: CarbonImmutable, last_end: CarbonImmutable}>  $days
     * @return list<AttendanceComplianceFinding>
     */
    private function checkWeeklyRest(int $userId, array $trips, array $days): array {
        $weeks = self::groupByWeek($days);
        if ($weeks === []) {
            return [];
        }
        $firstTripStart = $trips[0]['started_at'];
        $lastTripStart = $trips[count($trips) - 1]['started_at'];

        /** @var list<array{start: CarbonImmutable, end: CarbonImmutable, minutes:int}> $gaps */
        $gaps = [];
        for ($i = 1, $n = count($trips); $i < $n; $i++) {
            $start = $trips[$i - 1]['ended_at'];
            $end = $trips[$i]['started_at'];
            if ($end->greaterThan($start)) {
                $gaps[] = ['start' => $start, 'end' => $end, 'minutes' => (int) $start->diffInMinutes($end, false)];
            }
        }

        $findings = [];
        $previousReduced = false;
        foreach ($weeks as $week) {
            $windowStart = CarbonImmutable::parse($week['week_start']);
            $windowEnd = $windowStart->addWeek();
            if ($firstTripStart->greaterThanOrEqualTo($windowStart) || $lastTripStart->lessThan($windowEnd)) {
                $previousReduced = false;

                continue; // Datenrand — Randpause unbekannt.
            }

            $longest = 0;
            foreach ($gaps as $gap) {
                if ($gap['start']->lessThan($windowEnd) && $gap['end']->greaterThan($windowStart)) {
                    $longest = max($longest, $gap['minutes']);
                }
            }

            $class = DrivingTimeRules::classifyWeeklyRest($longest);
            if ($class === DrivingTimeRules::REST_REGULAR) {
                $previousReduced = false;

                continue;
            }
            if ($class === DrivingTimeRules::REST_INSUFFICIENT) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $week['week_end'],
                    kind: self::KIND_WEEKLY_REST,
                    severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                    value: $longest,
                    threshold: DrivingTimeRules::WEEKLY_REST_REDUCED_MINUTES,
                );
                $previousReduced = false;

                continue;
            }

            $findings[] = new AttendanceComplianceFinding(
                userId: $userId,
                date: $week['week_end'],
                kind: self::KIND_WEEKLY_REST,
                severity: $previousReduced ? AttendanceComplianceFinding::SEVERITY_ERROR : AttendanceComplianceFinding::SEVERITY_WARNING,
                value: $longest,
                threshold: DrivingTimeRules::WEEKLY_REST_MINUTES,
            );
            $previousReduced = true;
        }

        return $findings;
    }

    // ── Aggregation ──────────────────────────────────────────────────────

    /**
     * Chronologisch sortieren, leere/negative Fahrten verwerfen (Zusatzfelder
     * wie vehicle_id bleiben erhalten).
     *
     * @template T of array{started_at: CarbonImmutable, ended_at: CarbonImmutable}
     *
     * @param  list<T>  $trips
     * @return list<T>
     */
    public static function normalize(array $trips): array {
        $trips = array_values(array_filter(
            $trips,
            static fn(array $t): bool => $t['ended_at']->greaterThan($t['started_at']),
        ));
        usort($trips, static fn(array $a, array $b): int => $a['started_at'] <=> $b['started_at']);

        return $trips;
    }

    /**
     * Lenkminuten, erste Abfahrt und letzte Ankunft je Kalendertag (Starttag
     * der Fahrt; Fahrten über Mitternacht zählen zum Starttag).
     *
     * @param  list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>  $trips
     * @return array<string, array{minutes:int, first_start: CarbonImmutable, last_end: CarbonImmutable}>
     */
    public static function aggregateDays(array $trips): array {
        $days = [];
        foreach ($trips as $t) {
            $date = $t['started_at']->toDateString();
            $minutes = max(0, (int) $t['started_at']->diffInMinutes($t['ended_at'], false));
            if (! isset($days[$date])) {
                $days[$date] = ['minutes' => 0, 'first_start' => $t['started_at'], 'last_end' => $t['ended_at']];
            }
            $days[$date]['minutes'] += $minutes;
            if ($t['started_at']->lessThan($days[$date]['first_start'])) {
                $days[$date]['first_start'] = $t['started_at'];
            }
            if ($t['ended_at']->greaterThan($days[$date]['last_end'])) {
                $days[$date]['last_end'] = $t['ended_at'];
            }
        }
        ksort($days);

        return $days;
    }

    /**
     * Tage nach ISO-Woche gruppieren (Art. 4 lit. i: Mo 00:00 – So 24:00).
     *
     * @param  array<string, array{minutes:int, first_start: CarbonImmutable, last_end: CarbonImmutable}>  $days
     * @return array<string, array{minutes_by_date: array<string,int>, week_start: string, week_end: string, previous_key: string}>
     */
    public static function groupByWeek(array $days): array {
        $weeks = [];
        ksort($days);
        foreach ($days as $date => $agg) {
            $d = CarbonImmutable::parse($date);
            $key = $d->isoFormat('GGGG-[W]WW');
            if (! isset($weeks[$key])) {
                $weeks[$key] = [
                    'minutes_by_date' => [],
                    'week_start' => $d->startOfWeek()->toDateString(),
                    'week_end' => $d->endOfWeek()->toDateString(),
                    'previous_key' => $d->subWeek()->isoFormat('GGGG-[W]WW'),
                ];
            }
            $weeks[$key]['minutes_by_date'][$date] = $agg['minutes'];
        }

        return $weeks;
    }
}
