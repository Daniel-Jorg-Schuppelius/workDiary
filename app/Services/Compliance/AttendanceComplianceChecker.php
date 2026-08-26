<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceComplianceChecker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Organization;
use App\Services\Timekeeping\BreakRuleEvaluator;
use Carbon\CarbonImmutable;

/**
 * ArbZG-Compliance-Prüfung auf der TATSÄCHLICH erfassten Arbeitszeit
 * (Attendance/Ist), nicht auf der Dienstplan-Vorausschau (ScheduledShift,
 * vgl. {@see ShiftComplianceService}).
 *
 * BEWUSST PURE: keine DB-Zugriffe. Stempel-Spannen werden je Mitarbeiter
 * injiziert; der Controller lädt/aggregiert die Daten. Damit ist die reine
 * Schwellenprüfung isoliert testbar.
 *
 * DRY — die ArbZG-Schwellen stammen ausschliesslich aus dem Bestand und
 * werden NICHT hier neu definiert:
 *  - Tages-/Wochen-/Ruhezeit-Schwellen: {@see Organization::complianceSettings()}
 *    bzw. {@see Organization::COMPLIANCE_DEFAULTS} (max_hours_day=10,
 *    min_rest_hours=11, max_hours_week=48) — dieselbe Quelle wie die
 *    ScheduledShift-Regeln (MaxDailyHoursRule/RestPeriodRule/MaxWeeklyHoursRule).
 *  - Pflichtpausen: {@see BreakRuleEvaluator} (config timesheet.breaks.rules,
 *    ArbZG §4: 30 min ab 6h, 45 min ab 9h) — dieselbe Quelle wie der
 *    Tagesabschluss (DayClosureValidator::CHECK_BREAK_REQUIRED).
 *  - Tages-Netto (gross − Pausen): identische Logik wie
 *    DayClosureValidator::aggregate().
 *
 * Eingabeformat je Tag (recorded_at = Erfassungszeitpunkt, optional):
 *  list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int, recorded_at?: ?CarbonImmutable}>
 *
 * Zeitzone: Zeitstempel werden als Wandzeit interpretiert (Nachtfenster 23–6);
 * der Aufrufer übergibt sie in der Anzeige-Zeitzone (vgl. ComplianceScanService).
 *
 * Ausgabe: list<AttendanceComplianceFinding>.
 *
 * Bewusste MVP-Grenzen (Feature 131): JArbSchG/MuSchG werden NICHT geprüft
 * (Personenmerkmale Alter/Schwangerschaft fehlen im Datenmodell); die
 * Nachtzeit ist hart 23–6 Uhr (§2 Abs. 3 ArbZG), nicht konfigurierbar.
 */
final class AttendanceComplianceChecker {
    public const KIND_MAX_DAILY_HOURS = 'maxDailyHours';

    public const KIND_REST_PERIOD = 'restPeriod';

    public const KIND_BREAK_MISSING = 'breakMissing';

    public const KIND_MAX_WEEKLY_HOURS = 'maxWeeklyHours';

    /** MiLoG §17 / SchwarzArbG §2a: Erfassung > 7 Kalendertage nach der Leistung (MVP-695). */
    public const KIND_LATE_RECORDING = 'lateRecording';

    /** ArbZG §3 S. 2: 6-Monats-/24-Wochen-Durchschnitt > 8 h je Werktag (MVP-696). */
    public const KIND_SIX_MONTH_AVERAGE = 'sixMonthAverage';

    /** ArbZG §6 Abs. 2: Nachtarbeit (> 2 h in der Nachtzeit 23–6) über 8 h netto (MVP-696). */
    public const KIND_NIGHT_WORK = 'nightWork';

    /** ArbZG §11 Abs. 3: kein Ersatzruhetag binnen 2 Wochen (So) bzw. 8 Wochen (Feiertag) (MVP-696). */
    public const KIND_SUBSTITUTE_REST_DAY = 'substituteRestDay';

    /** ArbZG §11 Abs. 1: weniger als 15 beschäftigungsfreie Sonntage im Kalenderjahr erreichbar (MVP-696). */
    public const KIND_FREE_SUNDAYS = 'freeSundays';

    /** MiLoG §17 Abs. 1 / SchwarzArbG §2a: Aufzeichnungsfrist in Kalendertagen. */
    public const RECORDING_DEADLINE_DAYS = 7;

    /** ArbZG §3 S. 2: Ausgleichszeitraum 24 Wochen (Kalendertage). */
    public const AVERAGE_WINDOW_DAYS = 168;

    /** ArbZG §11 Abs. 3: Ersatzruhetag-Fenster für Feiertagsarbeit (8 Wochen). */
    public const HOLIDAY_REST_WINDOW_DAYS = 56;

    /** Mindest-Datenabdeckung, bevor der 24-Wochen-Durchschnitt bewertet wird. */
    private const AVERAGE_MIN_OBSERVED_DAYS = 28;

    /** ArbZG §3: 8 h je Werktag (Mo–Sa) als Durchschnittsgrenze. */
    private const AVERAGE_DAILY_MINUTES = 480;

    /** Nachtzeit 23–6 Uhr (§2 Abs. 3 ArbZG) — MVP bewusst hart, nicht konfigurierbar. */
    private const NIGHT_START_HOUR = 23;

    private const NIGHT_END_HOUR = 6;

    /** §2 Abs. 4 ArbZG: Nachtarbeit = mehr als 2 h innerhalb der Nachtzeit. */
    private const NIGHT_WORK_MIN_MINUTES = 120;

    /** §6 Abs. 2 ArbZG: 8 h; bis 10 h nur mit Monats-/4-Wochen-Ausgleich. */
    private const NIGHT_MAX_DAILY_MINUTES = 480;

    /** ArbZG §11 Abs. 3: Ersatzruhetag-Fenster für Sonntagsarbeit (2 Wochen). */
    private const SUNDAY_REST_WINDOW_DAYS = 14;

    /** ArbZG §11 Abs. 1: Mindestzahl beschäftigungsfreier Sonntage je Jahr. */
    private const MIN_FREE_SUNDAYS_PER_YEAR = 15;

    /**
     * @param  array{mode:string, max_hours_day:int, min_rest_hours:int, max_hours_week:int, max_consecutive_days:int, rules:array<string,bool>}  $settings  z. B. Organization::complianceSettings()
     */
    public function __construct(
        private readonly array $settings,
        private readonly BreakRuleEvaluator $breakRules,
    ) {}

    /** Bequemer Konstruktor mit den Compliance-Settings einer Organisation. */
    public static function forOrganization(?Organization $organization): self {
        $settings = $organization
            ? $organization->complianceSettings()
            : Organization::COMPLIANCE_DEFAULTS;

        return new self($settings, app(BreakRuleEvaluator::class));
    }

    /** Compliance global deaktiviert? Dann werden keine Verstöße ausgewiesen. */
    public function enabled(): bool {
        return $this->settings['mode'] !== Organization::COMPLIANCE_OFF;
    }

    /**
     * Prüft die Ist-Arbeitszeit EINES Mitarbeiters über einen Zeitraum.
     *
     * @param  array<string, list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int, recorded_at?: ?CarbonImmutable}>>  $attendancesByDate  Stempel-Spannen je Kalendertag (Y-m-d)
     * @param  list<string>  $holidays  Gesetzliche Feiertage (Y-m-d) im betrachteten Fenster — nur für §11 relevant
     * @return list<AttendanceComplianceFinding>
     */
    public function checkUser(int $userId, array $attendancesByDate, ?CarbonImmutable $now = null, array $holidays = []): array {
        if (! $this->enabled()) {
            return [];
        }
        $now ??= CarbonImmutable::now();
        $holidaySet = array_fill_keys($holidays, true);

        $findings = [];

        // Tages-Aggregate (Netto/Brutto/Pausen, erste Start- und letzte
        // Endzeit für die Ruhezeit, Nachtzeit-Minuten) je Kalendertag berechnen.
        $days = [];
        foreach ($attendancesByDate as $date => $spans) {
            $agg = $this->aggregateDay($spans, $now);
            if ($agg['gross'] <= 0) {
                continue;
            }
            $days[$date] = $agg;

            // 1. Tägliche Höchstarbeitszeit (Netto > max_hours_day, Standard 10h).
            foreach ($this->checkMaxDailyHours($userId, $date, $agg) as $f) {
                $findings[] = $f;
            }

            // 2. Pflichtpause (ArbZG §4, via BreakRuleEvaluator).
            foreach ($this->checkBreak($userId, $date, $agg) as $f) {
                $findings[] = $f;
            }

            // 5. MiLoG §17: Erfassung mehr als 7 Kalendertage nach der Leistung (MVP-695).
            foreach ($this->checkLateRecording($userId, $date, $spans) as $f) {
                $findings[] = $f;
            }

            // 6. ArbZG §6: Nachtarbeitstag über 8 h netto (MVP-696).
            foreach ($this->checkNightWork($userId, $date, $agg) as $f) {
                $findings[] = $f;
            }
        }

        // 3. Ruhezeit zwischen zwei Arbeitstagen (< min_rest_hours, Standard 11h).
        foreach ($this->checkRestPeriods($userId, $days) as $f) {
            $findings[] = $f;
        }

        // 4. Wöchentliche Höchstarbeitszeit (Ø > max_hours_week, Standard 48h).
        foreach ($this->checkWeeklyHours($userId, $days) as $f) {
            $findings[] = $f;
        }

        // 7. ArbZG §3 S. 2: rollierender 24-Wochen-Durchschnitt je Werktag (MVP-696).
        foreach ($this->checkSixMonthAverage($userId, $days) as $f) {
            $findings[] = $f;
        }

        // 8. ArbZG §11 Abs. 3: Ersatzruhetag nach Sonn-/Feiertagsarbeit (MVP-696).
        foreach ($this->checkSubstituteRestDays($userId, $days, $holidaySet, $now) as $f) {
            $findings[] = $f;
        }

        // 9. ArbZG §11 Abs. 1: 15 beschäftigungsfreie Sonntage je Kalenderjahr (MVP-696).
        foreach ($this->checkFreeSundays($userId, $days) as $f) {
            $findings[] = $f;
        }

        usort($findings, static fn(AttendanceComplianceFinding $a, AttendanceComplianceFinding $b): int => [$a->date, $a->kind] <=> [$b->date, $b->kind]);

        return $findings;
    }

    // ── Einzelprüfungen ──────────────────────────────────────────────────

    /**
     * @param  array{gross:int, breaks:int, net:int, night:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}  $agg
     * @return list<AttendanceComplianceFinding>
     */
    private function checkMaxDailyHours(int $userId, string $date, array $agg): array {
        $maxMinutes = $this->maxDailyMinutes();
        if ($agg['net'] <= $maxMinutes) {
            return [];
        }

        return [new AttendanceComplianceFinding(
            userId: $userId,
            date: $date,
            kind: self::KIND_MAX_DAILY_HOURS,
            severity: AttendanceComplianceFinding::SEVERITY_ERROR,
            value: $agg['net'],
            threshold: $maxMinutes,
        )];
    }

    /**
     * @param  array{gross:int, breaks:int, net:int, night:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}  $agg
     * @return list<AttendanceComplianceFinding>
     */
    private function checkBreak(int $userId, string $date, array $agg): array {
        // requiredMinutes() arbeitet auf der Brutto-Arbeitszeit (wie Tagesabschluss).
        $required = $this->breakRules->requiredMinutes($agg['gross']);
        if ($required <= 0 || $agg['breaks'] >= $required) {
            return [];
        }

        return [new AttendanceComplianceFinding(
            userId: $userId,
            date: $date,
            kind: self::KIND_BREAK_MISSING,
            severity: AttendanceComplianceFinding::SEVERITY_ERROR,
            value: $agg['breaks'],
            threshold: $required,
        )];
    }

    /**
     * @param  array<string, array{gross:int, breaks:int, net:int, night:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}>  $days
     * @return list<AttendanceComplianceFinding>
     */
    private function checkRestPeriods(int $userId, array $days): array {
        $minRestMinutes = (int) $this->settings['min_rest_hours'] * 60;
        ksort($days);
        $dates = array_keys($days);

        $findings = [];
        for ($i = 1, $n = count($dates); $i < $n; $i++) {
            $prevEnd = $days[$dates[$i - 1]]['last_end'];
            $currStart = $days[$dates[$i]]['first_start'];
            if ($prevEnd === null || $currStart === null || $currStart->lessThanOrEqualTo($prevEnd)) {
                continue;
            }
            $gapMinutes = (int) $prevEnd->diffInMinutes($currStart, false);
            if ($gapMinutes < $minRestMinutes) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $dates[$i],
                    kind: self::KIND_REST_PERIOD,
                    severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                    value: $gapMinutes,
                    threshold: $minRestMinutes,
                );
            }
        }

        return $findings;
    }

    /**
     * Wöchentliche Höchstarbeitszeit als Hinweis (ISO-Woche; ArbZG §3 bezieht
     * sich auf den Durchschnitt über den Bezugszeitraum — hier je ISO-Woche
     * summiert, analog MaxWeeklyHoursRule).
     *
     * @param  array<string, array{gross:int, breaks:int, net:int, night:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}>  $days
     * @return list<AttendanceComplianceFinding>
     */
    private function checkWeeklyHours(int $userId, array $days): array {
        $maxMinutes = (int) $this->settings['max_hours_week'] * 60;

        /** @var array<string, array{minutes:int, week_end:string}> $byWeek */
        $byWeek = [];
        foreach ($days as $date => $agg) {
            $d = CarbonImmutable::parse($date);
            $key = $d->isoFormat('GGGG-[W]WW');
            if (! isset($byWeek[$key])) {
                $byWeek[$key] = ['minutes' => 0, 'week_end' => $d->endOfWeek()->toDateString()];
            }
            $byWeek[$key]['minutes'] += $agg['net'];
        }

        $findings = [];
        foreach ($byWeek as $week) {
            if ($week['minutes'] > $maxMinutes) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $week['week_end'],
                    kind: self::KIND_MAX_WEEKLY_HOURS,
                    severity: AttendanceComplianceFinding::SEVERITY_WARNING,
                    value: $week['minutes'],
                    threshold: $maxMinutes,
                );
            }
        }

        return $findings;
    }

    /**
     * MiLoG §17 Abs. 1 / SchwarzArbG §2a (MVP-695): Arbeitszeit muss spätestens
     * 7 Kalendertage nach der Leistung erfasst sein. Verzug = Erfassungstag −
     * Leistungstag in Kalendertagen (Datumsvergleich, zeitzonenfest). Spannen
     * ohne recorded_at (Alt-/Importdaten ohne Aussagekraft) bleiben ungeprüft;
     * bei Importen entspricht recorded_at dem Importzeitpunkt — dokumentierte
     * Unschärfe, kein eigener Herkunfts-Sonderfall im MVP.
     *
     * @param  list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int, recorded_at?: ?CarbonImmutable}>  $spans
     * @return list<AttendanceComplianceFinding>
     */
    private function checkLateRecording(int $userId, string $date, array $spans): array {
        $workDay = CarbonImmutable::parse($date);

        $maxDelay = null;
        foreach ($spans as $s) {
            $recorded = $s['recorded_at'] ?? null;
            if ($recorded === null) {
                continue;
            }
            // Kalendertagsdifferenz über Datums-Strings — Offsets der
            // Anzeige-Zeitzone dürfen keine Bruchtage erzeugen.
            $delay = (int) $workDay->diffInDays(CarbonImmutable::parse($recorded->toDateString()), false);
            if ($maxDelay === null || $delay > $maxDelay) {
                $maxDelay = $delay;
            }
        }

        if ($maxDelay === null || $maxDelay <= self::RECORDING_DEADLINE_DAYS) {
            return [];
        }

        return [new AttendanceComplianceFinding(
            userId: $userId,
            date: $date,
            kind: self::KIND_LATE_RECORDING,
            severity: AttendanceComplianceFinding::SEVERITY_ERROR,
            value: $maxDelay,
            threshold: self::RECORDING_DEADLINE_DAYS,
        )];
    }

    /**
     * ArbZG §6 Abs. 2 (MVP-696): An Nachtarbeitstagen (> 2 h in der Nachtzeit
     * 23–6, §2 Abs. 3/4) sind über 8 h netto nur mit Ausgleich binnen eines
     * Monats/4 Wochen zulässig. Der Ausgleich selbst ist nicht nachvollziehbar
     * erfasst → Hinweis (warning) mit Ausgleichs-Kontext statt hartem Verstoß;
     * > 10 h deckt weiterhin KIND_MAX_DAILY_HOURS ab.
     *
     * @param  array{gross:int, breaks:int, net:int, night:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}  $agg
     * @return list<AttendanceComplianceFinding>
     */
    private function checkNightWork(int $userId, string $date, array $agg): array {
        if ($agg['night'] <= self::NIGHT_WORK_MIN_MINUTES || $agg['net'] <= self::NIGHT_MAX_DAILY_MINUTES) {
            return [];
        }

        return [new AttendanceComplianceFinding(
            userId: $userId,
            date: $date,
            kind: self::KIND_NIGHT_WORK,
            severity: AttendanceComplianceFinding::SEVERITY_WARNING,
            value: $agg['net'],
            threshold: self::NIGHT_MAX_DAILY_MINUTES,
        )];
    }

    /**
     * ArbZG §3 S. 2 (MVP-696): Werktägliche Arbeitszeit darf 8 h nur
     * überschreiten, wenn der Durchschnitt über 6 Monate/24 Wochen ≤ 8 h je
     * Werktag bleibt. Bewertet wird rollierend je ISO-Woche mit Arbeit:
     * Fenster = 24 Wochen bis Wochenende, geklemmt auf den ersten Datentag
     * (Teilfenster < 28 Tage werden übersprungen — zu wenig Abdeckung).
     * Werktage = Mo–Sa OHNE Feiertagsabzug (bewusste MVP-Vereinfachung:
     * Feiertage erhöhen den Nenner, der Durchschnitt sinkt — der Befund
     * bleibt eher aus, keine Falsch-Positiven).
     *
     * @param  array<string, array{gross:int, breaks:int, net:int, night:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}>  $days
     * @return list<AttendanceComplianceFinding>
     */
    private function checkSixMonthAverage(int $userId, array $days): array {
        if ($days === []) {
            return [];
        }
        ksort($days);
        $dates = array_keys($days);
        $firstDate = CarbonImmutable::parse($dates[0]);

        /** @var array<string, int> $netByDate */
        $netByDate = [];
        foreach ($days as $date => $agg) {
            $netByDate[$date] = $agg['net'];
        }

        // Bewertungszeitpunkte: Wochenenden (So) aller Wochen mit Arbeit.
        /** @var array<string, true> $weekEnds */
        $weekEnds = [];
        foreach ($dates as $date) {
            $weekEnds[CarbonImmutable::parse($date)->endOfWeek()->toDateString()] = true;
        }
        ksort($weekEnds);

        $findings = [];
        foreach (array_keys($weekEnds) as $weekEndStr) {
            $weekEnd = CarbonImmutable::parse($weekEndStr);
            $windowStart = $weekEnd->subDays(self::AVERAGE_WINDOW_DAYS - 1);
            if ($firstDate->greaterThan($windowStart)) {
                $windowStart = $firstDate;
            }
            $observedDays = (int) $windowStart->diffInDays($weekEnd, false) + 1;
            if ($observedDays < self::AVERAGE_MIN_OBSERVED_DAYS) {
                continue;
            }

            $workdays = 0;
            $sumNet = 0;
            for ($cursor = $windowStart; $cursor->lessThanOrEqualTo($weekEnd); $cursor = $cursor->addDay()) {
                if (! $cursor->isSunday()) {
                    $workdays++;
                }
                $sumNet += $netByDate[$cursor->toDateString()] ?? 0;
            }
            if ($workdays === 0) {
                continue;
            }

            $average = intdiv($sumNet, $workdays);
            if ($average > self::AVERAGE_DAILY_MINUTES) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $weekEndStr,
                    kind: self::KIND_SIX_MONTH_AVERAGE,
                    severity: AttendanceComplianceFinding::SEVERITY_WARNING,
                    value: $average,
                    threshold: self::AVERAGE_DAILY_MINUTES,
                );
            }
        }

        return $findings;
    }

    /**
     * ArbZG §11 Abs. 3 (MVP-696): Nach Sonntagsarbeit muss binnen eines den
     * Beschäftigungstag einschließenden Zeitraums von 2 Wochen (Feiertag:
     * 8 Wochen) ein Ersatzruhetag (beschäftigungsfreier Werktag Mo–Sa, kein
     * Feiertag) liegen. Geprüft wird erst, wenn das Fenster vollständig
     * verstrichen ist; Tage ohne Stempel-Daten gelten als frei — außerhalb
     * der Datenabdeckung entsteht damit NIE ein Falsch-Positiv, allenfalls
     * eine (dokumentierte) Untererfassung am Fensterrand.
     *
     * @param  array<string, array{gross:int, breaks:int, net:int, night:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}>  $days
     * @param  array<string, true>  $holidaySet  Feiertage (Y-m-d)
     * @return list<AttendanceComplianceFinding>
     */
    private function checkSubstituteRestDays(int $userId, array $days, array $holidaySet, CarbonImmutable $now): array {
        $today = $now->toDateString();

        $findings = [];
        foreach (array_keys($days) as $date) {
            $d = CarbonImmutable::parse($date);
            $isSunday = $d->isSunday();
            if (! $isSunday && ! isset($holidaySet[$date])) {
                continue;
            }

            // Feiertag auf Sonntag: kürzeres Sonntagsfenster gilt.
            $windowDays = $isSunday ? self::SUNDAY_REST_WINDOW_DAYS : self::HOLIDAY_REST_WINDOW_DAYS;
            $windowEnd = $d->addDays($windowDays - 1);
            if ($windowEnd->toDateString() >= $today) {
                continue; // Fenster läuft noch — Ersatzruhetag kann noch kommen.
            }

            $hasRestDay = false;
            for ($cursor = $d->addDay(); $cursor->lessThanOrEqualTo($windowEnd); $cursor = $cursor->addDay()) {
                $cs = $cursor->toDateString();
                if ($cursor->isSunday() || isset($holidaySet[$cs])) {
                    continue; // Sonntage/Feiertage sind keine Ersatzruhe-WERKtage.
                }
                if (! isset($days[$cs])) {
                    $hasRestDay = true;
                    break;
                }
            }

            if (! $hasRestDay) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $date,
                    kind: self::KIND_SUBSTITUTE_REST_DAY,
                    severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                    value: 0,
                    threshold: 1,
                );
            }
        }

        return $findings;
    }

    /**
     * ArbZG §11 Abs. 1 (MVP-696): Mindestens 15 beschäftigungsfreie Sonntage
     * je Kalenderjahr. Gemeldet wird erst, wenn 15 rechnerisch nicht mehr
     * erreichbar sind: (Sonntage des Jahres − beobachtete Arbeits-Sonntage)
     * < 15. Sonntagsarbeit außerhalb der Datenabdeckung bleibt unsichtbar
     * (Untererfassung, kein Falsch-Positiv); aussagekräftig ist die Regel
     * daher vor allem für Jahres-Zeiträume.
     *
     * @param  array<string, array{gross:int, breaks:int, net:int, night:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}>  $days
     * @return list<AttendanceComplianceFinding>
     */
    private function checkFreeSundays(int $userId, array $days): array {
        /** @var array<int, list<string>> $workedSundaysByYear */
        $workedSundaysByYear = [];
        foreach (array_keys($days) as $date) {
            $d = CarbonImmutable::parse($date);
            if ($d->isSunday()) {
                $workedSundaysByYear[$d->year][] = $date;
            }
        }

        $findings = [];
        ksort($workedSundaysByYear);
        foreach ($workedSundaysByYear as $year => $workedSundays) {
            $totalSundays = 0;
            $cursor = CarbonImmutable::parse(sprintf('%d-01-01', $year));
            while (! $cursor->isSunday()) {
                $cursor = $cursor->addDay();
            }
            while ($cursor->year === $year) {
                $totalSundays++;
                $cursor = $cursor->addWeek();
            }

            $maxFreeSundays = $totalSundays - count($workedSundays);
            if ($maxFreeSundays >= self::MIN_FREE_SUNDAYS_PER_YEAR) {
                continue;
            }

            sort($workedSundays);
            $findings[] = new AttendanceComplianceFinding(
                userId: $userId,
                date: (string) end($workedSundays),
                kind: self::KIND_FREE_SUNDAYS,
                severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                value: $maxFreeSundays,
                threshold: self::MIN_FREE_SUNDAYS_PER_YEAR,
            );
        }

        return $findings;
    }

    // ── Aggregation ──────────────────────────────────────────────────────

    /** Maximale Tages-Netto-Arbeitszeit in Minuten (Standard 10h, ArbZG §3). */
    private function maxDailyMinutes(): int {
        return (int) $this->settings['max_hours_day'] * 60;
    }

    /**
     * Brutto/Pausen/Netto eines Kalendertags — identische Rechnung wie
     * DayClosureValidator::aggregate() (Netto = max(0, brutto − Pausen));
     * zusätzlich die Minuten innerhalb der Nachtzeit 23–6 (§6-Prüfung).
     *
     * @param  list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int, recorded_at?: ?CarbonImmutable}>  $spans
     * @return array{gross:int, breaks:int, net:int, night:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}
     */
    private function aggregateDay(array $spans, CarbonImmutable $now): array {
        $gross = 0;
        $breaks = 0;
        $night = 0;
        $firstStart = null;
        $lastEnd = null;

        foreach ($spans as $s) {
            $start = $s['started_at'];
            $end = $s['ended_at'] ?? ($start->lessThan($now) ? $now : $start);
            $gross += max(0, (int) $start->diffInMinutes($end, false));
            $breaks += max(0, $s['break_minutes']);
            $night += $this->nightMinutes($start, $end);

            if ($firstStart === null || $start->lessThan($firstStart)) {
                $firstStart = $start;
            }
            if ($lastEnd === null || $end->greaterThan($lastEnd)) {
                $lastEnd = $end;
            }
        }

        return [
            'gross' => $gross,
            'breaks' => $breaks,
            'net' => max(0, $gross - $breaks),
            'night' => $night,
            'first_start' => $firstStart,
            'last_end' => $lastEnd,
        ];
    }

    /**
     * Überlappung einer Spanne mit der Nachtzeit 23–6 (§2 Abs. 3 ArbZG) in
     * Minuten — Wandzeit der übergebenen Zeitstempel; Fenster um den Starttag
     * herum decken auch Mitternachts-Übergänge ab.
     */
    private function nightMinutes(CarbonImmutable $start, CarbonImmutable $end): int {
        $minutes = 0;
        $anchor = $start->startOfDay();
        for ($offset = -1; $offset <= 1; $offset++) {
            $windowStart = $anchor->addDays($offset)->setTime(self::NIGHT_START_HOUR, 0);
            $windowEnd = $anchor->addDays($offset + 1)->setTime(self::NIGHT_END_HOUR, 0);
            $overlapStart = $start->greaterThan($windowStart) ? $start : $windowStart;
            $overlapEnd = $end->lessThan($windowEnd) ? $end : $windowEnd;
            if ($overlapEnd->greaterThan($overlapStart)) {
                $minutes += (int) $overlapStart->diffInMinutes($overlapEnd, false);
            }
        }

        return $minutes;
    }
}
