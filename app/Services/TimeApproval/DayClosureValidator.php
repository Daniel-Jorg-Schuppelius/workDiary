<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayClosureValidator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeApproval;

use Carbon\CarbonImmutable;

/**
 * Konsistenzprüfungen für den Tagesabschluss (MVP-015,
 * docs/tagesabschluss.md §4) — bewusst PURE: keine DB-Zugriffe, alle
 * Daten (Stempel, Buchungen, Tagessoll, Pausenregeln) werden injiziert,
 * damit die 7 Checks isoliert testbar sind.
 *
 * Eingabeformate:
 *  - Anwesenheit: list<array{started_at: CarbonImmutable,
 *      ended_at: ?CarbonImmutable, break_minutes: int}>
 *    (offener Stempel = ended_at null; wird live bis $now gerechnet)
 *  - Buchungen: list<array{minutes:int, billable:bool, has_comment:bool,
 *      counted:bool}> — `counted` markiert Arten, die zur Ist-Arbeitszeit
 *    zählen (Work/Travel, keine Pausen/Abwesenheit; vgl. FlexCalculator).
 *
 * Ergebnis: nach Schweregrad sortierte Liste
 *  list<array{code:string, severity:string, meta:array<string, int|string>}>.
 */
class DayClosureValidator {
    public const SEVERITY_BLOCKING = 'blocking'; // ⛔ — verhindert day.close

    public const SEVERITY_WARNING = 'warning';   // ⚠ — Hinweis, blockiert nicht

    public const CHECK_ATTENDANCE_MISSING_CLOSE = 'attendance.missing_close';

    public const CHECK_TIME_UNALLOCATED = 'time.unallocated_minutes';

    public const CHECK_BREAK_REQUIRED = 'break.required';

    public const CHECK_GAP_IN_ATTENDANCE = 'time.gap_in_attendance';

    public const CHECK_BALANCE_THRESHOLD = 'balance.threshold';

    public const CHECK_ENTRY_MISSING_COMMENT = 'entry.missing_comment';

    public const CHECK_WORKTIME_OVERRUN = 'worktime.overrun';

    /**
     * @param  list<array{after_minutes:int, required_minutes:int}>  $breakRules  gesetzliche Pausenregeln
     *                                                                            (z. B. ArbZG §4: 30 min ab 6h, 45 min ab 9h), vgl. BreakRuleEvaluator::rules()
     * @param  int  $unallocatedToleranceMinutes  Toleranz Netto−Verbucht (§4: 5 min)
     * @param  int  $gapThresholdMinutes  Anwesenheits-Lücke ohne Pausen-Marker (§4: 15 min)
     * @param  int  $balanceThresholdMinutes  Tages-Saldo-Schwelle (§4: ±2 h)
     * @param  int  $overrunThresholdMinutes  Netto-Arbeitszeit-Obergrenze (§4: 10 h, ArbZG)
     */
    public function __construct(
        private readonly array $breakRules = [],
        private readonly int $unallocatedToleranceMinutes = 5,
        private readonly int $gapThresholdMinutes = 15,
        private readonly int $balanceThresholdMinutes = 120,
        private readonly int $overrunThresholdMinutes = 600,
    ) {}

    /**
     * Führt alle 7 Checks aus §4 aus, sortiert nach Schweregrad (⛔ vor ⚠).
     *
     * @param  list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>  $attendances
     * @param  list<array{minutes:int, billable:bool, has_comment:bool, counted:bool}>  $entries
     * @param  int  $targetMinutes  Tagessoll laut Arbeitszeitmodell
     * @return list<array{code:string, severity:string, meta:array<string, int|string>}>
     */
    public function validate(array $attendances, array $entries, int $targetMinutes, ?CarbonImmutable $now = null): array {
        $now ??= CarbonImmutable::now();
        $agg = $this->aggregate($attendances, $entries, $now);
        $issues = [];

        // 1. attendance.missing_close ⛔ — Stempeluhr noch offen.
        if ($agg['open_count'] > 0) {
            $issues[] = $this->issue(self::CHECK_ATTENDANCE_MISSING_CLOSE, self::SEVERITY_BLOCKING, [
                'open' => $agg['open_count'],
            ]);
        }

        // 2. time.unallocated_minutes ⛔ — Netto-Anwesenheit − Buchungen > Toleranz.
        $unallocated = $agg['net'] - $agg['booked'];
        if ($unallocated > $this->unallocatedToleranceMinutes) {
            $issues[] = $this->issue(self::CHECK_TIME_UNALLOCATED, self::SEVERITY_BLOCKING, [
                'minutes' => $unallocated,
            ]);
        }

        // 3. break.required ⛔ — Pflichtpause (nach Brutto-Zeit) unterschritten.
        $requiredBreak = $this->requiredBreakMinutes($agg['gross']);
        if ($agg['gross'] > 0 && $agg['breaks'] < $requiredBreak) {
            $issues[] = $this->issue(self::CHECK_BREAK_REQUIRED, self::SEVERITY_BLOCKING, [
                'required' => $requiredBreak,
                'taken' => $agg['breaks'],
                'missing' => $requiredBreak - $agg['breaks'],
            ]);
        }

        // 4. time.gap_in_attendance ⚠ — Lücke zwischen Stempel-Spannen
        //    > Schwelle, die nicht durch erfasste Pausenminuten gedeckt ist.
        foreach ($this->gaps($attendances) as $gap) {
            if ($gap['minutes'] > $this->gapThresholdMinutes && $agg['breaks'] < $gap['minutes']) {
                $issues[] = $this->issue(self::CHECK_GAP_IN_ATTENDANCE, self::SEVERITY_WARNING, $gap);
            }
        }

        // 5. balance.threshold ⚠ — Tages-Saldo (Netto − Soll) über ±Schwelle.
        $balance = $agg['net'] - $targetMinutes;
        if (abs($balance) > $this->balanceThresholdMinutes) {
            $issues[] = $this->issue(self::CHECK_BALANCE_THRESHOLD, self::SEVERITY_WARNING, [
                'balance' => $balance,
            ]);
        }

        // 6. entry.missing_comment ⚠ — abrechnungsrelevante Buchung ohne Kommentar.
        $missingComments = 0;
        foreach ($entries as $entry) {
            if ($entry['billable'] && ! $entry['has_comment']) {
                $missingComments++;
            }
        }
        if ($missingComments > 0) {
            $issues[] = $this->issue(self::CHECK_ENTRY_MISSING_COMMENT, self::SEVERITY_WARNING, [
                'count' => $missingComments,
            ]);
        }

        // 7. worktime.overrun ⚠ — Netto-Arbeitszeit über der ArbZG-Obergrenze.
        if ($agg['net'] > $this->overrunThresholdMinutes) {
            $issues[] = $this->issue(self::CHECK_WORKTIME_OVERRUN, self::SEVERITY_WARNING, [
                'minutes' => $agg['net'],
            ]);
        }

        usort($issues, static fn(array $a, array $b): int => ($a['severity'] === $b['severity'])
            ? 0
            : ($a['severity'] === self::SEVERITY_BLOCKING ? -1 : 1));

        return $issues;
    }

    /**
     * @param  list<array{code:string, severity:string, meta:array<string, int|string>}>  $issues
     */
    public function hasBlocking(array $issues): bool {
        foreach ($issues as $issue) {
            if ($issue['severity'] === self::SEVERITY_BLOCKING) {
                return true;
            }
        }

        return false;
    }

    /**
     * Aggregiert Brutto/Pause/Netto/Verbucht — von validate() UND vom
     * DayCloseService (Bilanz, §2.5) genutzt, damit beide identisch rechnen.
     *
     * @param  list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>  $attendances
     * @param  list<array{minutes:int, billable:bool, has_comment:bool, counted:bool}>  $entries
     * @return array{gross:int, breaks:int, net:int, booked:int, open_count:int}
     */
    public function aggregate(array $attendances, array $entries, ?CarbonImmutable $now = null): array {
        $now ??= CarbonImmutable::now();
        $gross = 0;
        $breaks = 0;
        $openCount = 0;

        foreach ($attendances as $a) {
            $start = $a['started_at'];
            $end = $a['ended_at'];
            if ($end === null) {
                $openCount++;
                $end = $start->lessThan($now) ? $now : $start;
            }
            $gross += max(0, (int) $start->diffInMinutes($end, false));
            $breaks += max(0, $a['break_minutes']);
        }

        $booked = 0;
        foreach ($entries as $entry) {
            if ($entry['counted']) {
                $booked += max(0, $entry['minutes']);
            }
        }

        return [
            'gross' => $gross,
            'breaks' => $breaks,
            'net' => max(0, $gross - $breaks),
            'booked' => $booked,
            'open_count' => $openCount,
        ];
    }

    /**
     * Lokalisierte Meldung zu einem Check-Ergebnis (für Sektion D / Banner).
     *
     * @param  array{code:string, severity:string, meta:array<string, int|string>}  $issue
     */
    public function messageFor(array $issue): string {
        $meta = $issue['meta'];

        // Check-Codes (z. B. `attendance.missing_close`) werden 1:1 als
        // verschachtelte Keys in lang/*/day-close.php (`check.*`) abgebildet.
        return match ($issue['code']) {
            self::CHECK_ATTENDANCE_MISSING_CLOSE => (string) __('day-close.check.attendance.missing_close'),
            self::CHECK_TIME_UNALLOCATED => (string) __('day-close.check.time.unallocated_minutes', ['minutes' => $meta['minutes'] ?? 0]),
            self::CHECK_BREAK_REQUIRED => (string) __('day-close.check.break.required', ['taken' => $meta['taken'] ?? 0, 'required' => $meta['required'] ?? 0]),
            self::CHECK_GAP_IN_ATTENDANCE => (string) __('day-close.check.time.gap_in_attendance', ['minutes' => $meta['minutes'] ?? 0]),
            self::CHECK_BALANCE_THRESHOLD => (string) __('day-close.check.balance.threshold', ['hours' => number_format(((int) ($meta['balance'] ?? 0)) / 60, 1)]),
            self::CHECK_ENTRY_MISSING_COMMENT => (string) __('day-close.check.entry.missing_comment', ['count' => $meta['count'] ?? 0]),
            self::CHECK_WORKTIME_OVERRUN => (string) __('day-close.check.worktime.overrun', ['minutes' => $meta['minutes'] ?? 0]),
            default => (string) __('day-close.check.unknown', ['code' => $issue['code']]),
        };
    }

    // ── intern ─────────────────────────────────────────────────────────

    /** Pflichtpause für eine Brutto-Arbeitszeit laut injizierten Regeln. */
    private function requiredBreakMinutes(int $grossMinutes): int {
        $required = 0;
        foreach ($this->breakRules as $rule) {
            if ($grossMinutes > (int) $rule['after_minutes']) {
                $required = max($required, (int) $rule['required_minutes']);
            }
        }

        return $required;
    }

    /**
     * Lücken zwischen aufeinanderfolgenden, beendeten Stempel-Spannen.
     *
     * @param  list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>  $attendances
     * @return list<array{minutes:int, from:string, to:string}>
     */
    private function gaps(array $attendances): array {
        $spans = array_values(array_filter(
            $attendances,
            static fn(array $a): bool => $a['ended_at'] !== null,
        ));
        usort($spans, static fn(array $a, array $b): int => $a['started_at'] <=> $b['started_at']);

        $gaps = [];
        for ($i = 1, $n = count($spans); $i < $n; $i++) {
            // ended_at ist nach dem Filter oben garantiert nicht null.
            $prevEnd = $spans[$i - 1]['ended_at'] ?? null;
            $nextStart = $spans[$i]['started_at'];
            if ($prevEnd === null || $nextStart->lessThanOrEqualTo($prevEnd)) {
                continue;
            }
            $gaps[] = [
                'minutes' => (int) $prevEnd->diffInMinutes($nextStart, false),
                'from' => $prevEnd->toIso8601String(),
                'to' => $nextStart->toIso8601String(),
            ];
        }

        return $gaps;
    }

    /**
     * @param  array<string, int|string>  $meta
     * @return array{code:string, severity:string, meta:array<string, int|string>}
     */
    private function issue(string $code, string $severity, array $meta): array {
        return ['code' => $code, 'severity' => $severity, 'meta' => $meta];
    }
}
