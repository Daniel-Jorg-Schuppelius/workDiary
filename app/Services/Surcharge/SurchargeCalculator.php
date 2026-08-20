<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Surcharge;

use App\Enums\Surcharge\SurchargeKind;
use App\Models\Surcharge\SurchargeRule;
use App\Services\HolidayService;
use App\Support\Setting;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Support\Collection;

/**
 * Zerlegt einen Arbeitszeitraum in zuschlagsfähige Segmente (Feature 005, MVP).
 *
 * Eingabe ist ein beliebiger Zeitraum (z. B. Attendance started_at→ended_at
 * oder ein TimeEntry-Intervall) plus die Zuschlagsregeln der Organisation;
 * Ausgabe sind {@see SurchargeShare}-Anteile: je Regel und Kalendertag die
 * Minuten, die in den Geltungsbereich der Regel fallen.
 *
 * Regel-Semantik:
 *  - night/custom: Zeitfenster window_start–window_end; Fenster über
 *    Mitternacht (23:00–06:00) werden pro Kalendertag in 00:00–06:00 und
 *    23:00–24:00 gesplittet (Minuten zählen zum Tag, in dem sie liegen).
 *  - saturday/sunday: ganzer Wochentag.
 *  - holiday: ganzer Tag laut {@see HolidayService} (Yasumi-Provider gemäß
 *    dem mandantenbewussten Rechtsraum Setting::get('holidays.provider') +
 *    org-eigene Holiday-Einträge — dieselbe Quelle wie die Compliance-Regeln).
 *  - valid_from/valid_until (inklusiv) und active werden pro Kalendertag geprüft.
 *
 * Stacking-Modus (Audit 2026-08, W4.3) — Org-Setting `surcharge.stacking`:
 *  - `highest` (Default, Bestandsverhalten): Überlappen mehrere Regeln
 *    (z. B. Nacht + Sonntag), gewinnt der höchste Prozentsatz; bei
 *    Gleichstand die höhere priority, danach die ältere Regel (kleinere id).
 *  - `add`: Die Minuten zählen für JEDE zutreffende Regel — die Zuschläge
 *    addieren sich also. Nach § 3b EStG ist das für Nacht + Sonntag/Feiertag
 *    zulässig; ob es gewollt ist, entscheidet die steuerliche Praxis der
 *    Organisation. Der Default bleibt bewusst `highest`, damit sich für
 *    Bestandsmandanten nichts still ändert.
 *
 * Reine, seiteneffektfreie Logik — einzig der HolidayService wird für
 * Feiertags-Lookups befragt.
 */
class SurchargeCalculator {
    /** Feiertags-Rechtsraum des aktuellen calculate()-Laufs (MVP-513). */
    private ?string $holidayProvider = null;

    /** Stacking-Modus des aktuellen Laufs: 'highest' | 'add' (W4.3). */
    private string $stacking = 'highest';

    public function __construct(
        private readonly HolidayService $holidays,
    ) {}

    /**
     * Berechnet die zuschlagsfähigen Minuten je Regel und Kalendertag.
     *
     * @param  CarbonInterface  $start  Beginn des (Netto-)Intervalls
     * @param  CarbonInterface  $end  Ende des Intervalls (exklusiv)
     * @param  Collection<int, SurchargeRule>  $rules  Regeln der Organisation
     * @param  string|null  $holidayProvider  Feiertags-Rechtsraum des Einsatzorts (MVP-513); null = Org-Einstellung
     * @return list<SurchargeShare>
     */
    public function calculate(CarbonInterface $start, CarbonInterface $end, Collection $rules, ?string $holidayProvider = null): array {
        $this->holidayProvider = $holidayProvider;
        // Mandantenbewusst wie der Feiertags-Rechtsraum; unbekannte Werte
        // fallen auf das Bestandsverhalten zurueck.
        $mode = (string) Setting::get('surcharge.stacking', (string) config('surcharge.stacking', 'highest'));
        $this->stacking = $mode === 'add' ? 'add' : 'highest';
        $start = CarbonImmutable::instance($start);
        $end = CarbonImmutable::instance($end);

        if ($end->lessThanOrEqualTo($start) || $rules->isEmpty()) {
            return [];
        }

        /** @var array<string, array{rule: SurchargeRule, date: string, minutes: int}> $acc */
        $acc = [];

        // Tag-Scheiben: [start, end) an Mitternachtsgrenzen splitten, damit
        // Wochentag/Feiertag/Gültigkeit pro Kalendertag eindeutig sind.
        $cursor = $start;
        while ($cursor->lessThan($end)) {
            $dayEnd = $cursor->addDay()->startOfDay();
            $sliceEnd = $dayEnd->lessThan($end) ? $dayEnd : $end;

            $this->accumulateDaySlice($cursor, $sliceEnd, $rules, $acc);

            $cursor = $sliceEnd;
        }

        ksort($acc);

        return array_values(array_map(
            static fn(array $row): SurchargeShare => new SurchargeShare($row['rule'], $row['date'], $row['minutes']),
            $acc,
        ));
    }

    /**
     * Verarbeitet eine Tag-Scheibe [sliceStart, sliceEnd) innerhalb EINES
     * Kalendertags: Regel-Intervalle bestimmen, Überlappungen auflösen
     * (max-Prozentsatz gewinnt) und Minuten akkumulieren.
     *
     * @param  Collection<int, SurchargeRule>  $rules
     * @param  array<string, array{rule: SurchargeRule, date: string, minutes: int}>  $acc
     */
    private function accumulateDaySlice(CarbonImmutable $sliceStart, CarbonImmutable $sliceEnd, Collection $rules, array &$acc): void {
        $date = $sliceStart->toDateString();
        $dayStart = $sliceStart->startOfDay();
        $fromMin = $this->minutesSinceMidnight($dayStart, $sliceStart);
        $toMin = $this->minutesSinceMidnight($dayStart, $sliceEnd);

        // Pro Regel: anwendbare Minuten-Intervalle [a,b) innerhalb der Scheibe.
        /** @var array<int, array{rule: SurchargeRule, intervals: list<array{0:int,1:int}>}> $applicable */
        $applicable = [];
        foreach ($rules as $rule) {
            if (! $rule->active || ! $rule->appliesOn($sliceStart)) {
                continue;
            }

            $intervals = $this->ruleIntervalsForDay($rule, $sliceStart, $fromMin, $toMin);
            if ($intervals !== []) {
                $applicable[] = ['rule' => $rule, 'intervals' => $intervals];
            }
        }

        if ($applicable === []) {
            return;
        }

        // Elementar-Segmente aus allen Intervallgrenzen bilden und je Segment
        // die Gewinner-Regel (max Prozentsatz → max priority → min id) küren.
        $bounds = [];
        foreach ($applicable as $entry) {
            foreach ($entry['intervals'] as [$a, $b]) {
                $bounds[$a] = true;
                $bounds[$b] = true;
            }
        }
        $bounds = array_keys($bounds);
        sort($bounds);

        for ($i = 0, $n = count($bounds) - 1; $i < $n; $i++) {
            $a = $bounds[$i];
            $b = $bounds[$i + 1];
            if ($b <= $a) {
                continue;
            }

            // Additives Stacking: die Minuten zaehlen fuer JEDE zutreffende
            // Regel; ohne Stacking kuert eine Regel das Segment.
            if ($this->stacking === 'add') {
                foreach ($applicable as $entry) {
                    if (! $this->covers($entry['intervals'], $a, $b)) {
                        continue;
                    }
                    $this->addMinutes($acc, $date, $entry['rule'], $b - $a);
                }

                continue;
            }

            $winner = null;
            foreach ($applicable as $entry) {
                if (! $this->covers($entry['intervals'], $a, $b)) {
                    continue;
                }
                if ($winner === null || $this->beats($entry['rule'], $winner)) {
                    $winner = $entry['rule'];
                }
            }

            if ($winner === null) {
                continue;
            }

            $this->addMinutes($acc, $date, $winner, $b - $a);
        }
    }

    /**
     * Minuten einer Regel an einem Tag akkumulieren (Schluessel: Tag + Regel).
     *
     * @param  array<string, array{rule: SurchargeRule, date: string, minutes: int}>  $acc
     */
    private function addMinutes(array &$acc, string $date, SurchargeRule $rule, int $minutes): void {
        $key = $date . '|' . str_pad((string) $rule->id, 10, '0', STR_PAD_LEFT);
        if (! isset($acc[$key])) {
            $acc[$key] = ['rule' => $rule, 'date' => $date, 'minutes' => 0];
        }
        $acc[$key]['minutes'] += $minutes;
    }

    /**
     * Anwendbare Minuten-Intervalle einer Regel innerhalb [$fromMin, $toMin)
     * eines konkreten Kalendertags (Minuten seit Mitternacht).
     *
     * @return list<array{0:int,1:int}>
     */
    private function ruleIntervalsForDay(SurchargeRule $rule, CarbonImmutable $day, int $fromMin, int $toMin): array {
        $fullDay = match ($rule->kind) {
            SurchargeKind::Saturday => $day->isSaturday(),
            SurchargeKind::Sunday => $day->isSunday(),
            SurchargeKind::Holiday => $this->holidays->isHoliday($day, $this->holidayProvider),
            SurchargeKind::Night, SurchargeKind::Custom => null,
            // M4: keine Intervall-Regeln — eigene Quellzeiten, Aggregation im
            // Zeit-Export; sie zerlegen nie Attendance-Intervalle.
            SurchargeKind::OnCall, SurchargeKind::Standby, SurchargeKind::Overtime => false,
        };

        if ($fullDay === true) {
            return [[$fromMin, $toMin]];
        }
        if ($fullDay === false) {
            return [];
        }

        // night/custom: Fenster ggf. über Mitternacht in Tagesabschnitte zerlegen.
        $ws = $this->timeToMinutes($rule->window_start);
        $we = $this->timeToMinutes($rule->window_end);
        if ($ws === null || $we === null || $ws === $we) {
            return [];
        }

        $windows = $ws < $we
            ? [[$ws, $we]]
            : [[0, $we], [$ws, 1440]]; // über Mitternacht: 00:00–we und ws–24:00

        $intervals = [];
        foreach ($windows as [$a, $b]) {
            $a = max($a, $fromMin);
            $b = min($b, $toMin);
            if ($b > $a) {
                $intervals[] = [$a, $b];
            }
        }

        return $intervals;
    }

    /**
     * Liegt [a,b) vollständig in einem der Intervalle?
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    private function covers(array $intervals, int $a, int $b): bool {
        foreach ($intervals as [$x, $y]) {
            if ($x <= $a && $b <= $y) {
                return true;
            }
        }

        return false;
    }

    /** Gewinnt $candidate gegen $current? (max %, dann max priority, dann min id) */
    private function beats(SurchargeRule $candidate, SurchargeRule $current): bool {
        $cp = (float) $candidate->percentage;
        $wp = (float) $current->percentage;
        if ($cp !== $wp) {
            return $cp > $wp;
        }
        if ($candidate->priority !== $current->priority) {
            return $candidate->priority > $current->priority;
        }

        return (int) $candidate->id < (int) $current->id;
    }

    private function minutesSinceMidnight(CarbonImmutable $dayStart, CarbonImmutable $moment): int {
        // Wanduhr-Minuten statt Echtzeit-Differenz: an DST-Tagen (23/25 h)
        // müssen die Fenstergrenzen (timeToMinutes, '23:00' → 1380) weiterhin
        // die lokale Uhrzeit treffen, nicht die verstrichenen Minuten.
        if ($moment->greaterThan($dayStart) && $moment->toDateString() !== $dayStart->toDateString()) {
            return 1440; // Scheibenende = Mitternacht des Folgetags
        }

        return $moment->hour * 60 + $moment->minute;
    }

    /** 'H:i' / 'H:i:s' → Minuten seit Mitternacht (null bei fehlendem Wert). */
    private function timeToMinutes(?string $time): ?int {
        if ($time === null || $time === '') {
            return null;
        }
        $parts = explode(':', $time);

        return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
    }
}
