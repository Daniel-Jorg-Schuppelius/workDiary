<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendancePlausibilityScanService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\Vacation\VacationStatus;
use App\Models\{Attendance, Organization, ScheduledShift, SickLeave, User, Vacation, WorkSchedule};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Plausibilitäts-Befunde der Stempelzeiten (MVP-519, „Ungeklärte Fälle"):
 * Tage, deren Anwesenheiten ohne manuelle Klärung nicht schlüssig sind —
 * vergessene Geht-Stempelung, Stempelung an planmäßig freiem Tag oder trotz
 * genehmigter ganztägiger Abwesenheit, Rahmenzeit-Überschreitung jenseits der
 * Bagatellgrenze. Ergebnis-Format und Persistenz teilen sich die Pipeline mit
 * dem ArbZG-Scan ({@see ComplianceScanService} → {@see ComplianceFindingRecorder},
 * eigene Kategorie {@see self::CATEGORY}); Klärung läuft über den bestehenden
 * Acknowledge-Workflow der Verstoß-Historie.
 */
final class AttendancePlausibilityScanService {
    public const CATEGORY = 'plausibility';

    /** Geht-Stempelung fehlt (Anwesenheit über den Kalendertag hinaus offen). */
    public const KIND_MISSING_CHECKOUT = 'missingCheckout';

    /** Stempelung an einem laut Arbeitszeitmodell/Dienstplan freien Tag. */
    public const KIND_FREE_DAY_STAMP = 'freeDayStamp';

    /** Stempelung trotz genehmigter ganztägiger Abwesenheit (Urlaub/Krank). */
    public const KIND_ABSENCE_STAMP = 'absenceStamp';

    /** Stempelzeit außerhalb der Rahmenzeit über der Bagatellgrenze. */
    public const KIND_FRAME_TIME = 'attendanceFrameTime';

    /**
     * @return array<int, list<AttendanceComplianceFinding>>  Befunde je user_id
     */
    public function findingsForRange(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $settings = $organization->complianceSettings();
        if ($settings['mode'] === Organization::COMPLIANCE_OFF) {
            return [];
        }
        /** @var array<string, bool> $rules */
        $rules = $settings['rules'];
        $ruleOn = static fn (string $key): bool => (bool) ($rules[$key] ?? true);
        $tolerance = max(0, (int) $settings['frame_tolerance_minutes']);

        // Org-Grenze: User hat KEINEN globalen OrganizationScope (vgl. ComplianceScanService).
        $userIds = User::query()
            ->where('organization_id', $organization->getKey())
            ->pluck('id')
            ->map(static fn ($v): int => (int) $v)
            ->all();
        if ($userIds === []) {
            return [];
        }

        $tz = Tz::current();
        $today = CarbonImmutable::now($tz)->toDateString();
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        /** @var Collection<int, Attendance> $attendances */
        $attendances = Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$fromStr, $toStr])
            ->where('status', '!=', AttendanceStatus::Cancelled->value)
            ->whereNotNull('started_at')
            ->orderBy('started_at')
            ->get();
        if ($attendances->isEmpty()) {
            return [];
        }

        $schedulesByUser = WorkSchedule::query()
            ->whereIn('user_id', $userIds)
            ->where('valid_from', '<=', $toStr)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $fromStr))
            ->orderBy('valid_from')
            ->get()
            ->groupBy('user_id');

        // Geplante Dienste als Menge "uid|Y-m-d" (stornierte zählen nicht).
        $shiftSet = [];
        ScheduledShift::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$fromStr, $toStr])
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->get(['user_id', 'date'])
            ->each(function (ScheduledShift $s) use (&$shiftSet): void {
                $shiftSet[$s->user_id . '|' . $s->date->toDateString()] = true;
            });

        // Genehmigte ganztägige Abwesenheiten als Menge "uid|Y-m-d".
        $absenceSet = [];
        $expand = function (int $uid, CarbonImmutable $start, CarbonImmutable $end) use (&$absenceSet, $fromStr, $toStr): void {
            $cursor = $start->lessThan(CarbonImmutable::parse($fromStr)) ? CarbonImmutable::parse($fromStr) : $start;
            $limit = $end->greaterThan(CarbonImmutable::parse($toStr)) ? CarbonImmutable::parse($toStr) : $end;
            while ($cursor->lessThanOrEqualTo($limit)) {
                $absenceSet[$uid . '|' . $cursor->toDateString()] = true;
                $cursor = $cursor->addDay();
            }
        };
        Vacation::query()
            ->whereIn('user_id', $userIds)
            ->where('status', VacationStatus::Approved->value)
            ->where('start_date', '<=', $toStr)
            ->where('end_date', '>=', $fromStr)
            ->get(['user_id', 'start_date', 'end_date'])
            ->each(fn (Vacation $v) => $expand((int) $v->user_id, CarbonImmutable::parse($v->start_date->toDateString()), CarbonImmutable::parse($v->end_date->toDateString())));
        SickLeave::query()
            ->whereIn('user_id', $userIds)
            ->where('start_date', '<=', $toStr)
            ->where('end_date', '>=', $fromStr)
            ->get(['user_id', 'start_date', 'end_date'])
            ->each(fn (SickLeave $s) => $expand((int) $s->user_id, CarbonImmutable::parse($s->start_date->toDateString()), CarbonImmutable::parse($s->end_date->toDateString())));

        // Spannen je Nutzer/Kalendertag (Anzeige-Zeitzone, wie Attendance::saving()).
        /** @var array<int, array<string, array{open: bool, open_start: ?CarbonImmutable, gross: int, before: int, after: int}>> $byUserDate */
        $byUserDate = [];
        foreach ($attendances as $a) {
            if ($a->started_at === null) {
                continue;
            }
            $start = CarbonImmutable::parse($a->started_at->toIso8601String())->setTimezone($tz);
            $dateKey = $start->toDateString();
            $slot = $byUserDate[(int) $a->user_id][$dateKey] ?? [
                'open' => false, 'open_start' => null, 'gross' => 0, 'before' => 0, 'after' => 0,
            ];

            if ($a->ended_at === null) {
                if ($a->status === AttendanceStatus::Open) {
                    $slot['open'] = true;
                    $slot['open_start'] ??= $start;
                }
            } else {
                $end = CarbonImmutable::parse($a->ended_at->toIso8601String())->setTimezone($tz);
                $slot['gross'] += max(0, (int) $start->diffInMinutes($end, false));

                $schedule = $this->scheduleFor($schedulesByUser->get((int) $a->user_id, collect()), $dateKey);
                if ($schedule !== null && $schedule->frame_start !== null && $schedule->frame_end !== null) {
                    $frameStart = $start->setTimeFromTimeString($schedule->frame_start);
                    $frameEnd = $start->setTimeFromTimeString($schedule->frame_end);
                    if ($frameEnd->greaterThan($frameStart)) {
                        if ($start->lessThan($frameStart)) {
                            $slot['before'] += min(
                                (int) $start->diffInMinutes($frameStart, false),
                                (int) $start->diffInMinutes($end, false),
                            );
                        }
                        if ($end->greaterThan($frameEnd)) {
                            $slot['after'] += min(
                                (int) $frameEnd->diffInMinutes($end, false),
                                (int) $start->diffInMinutes($end, false),
                            );
                        }
                    }
                }
            }

            $byUserDate[(int) $a->user_id][$dateKey] = $slot;
        }

        /** @var array<int, list<AttendanceComplianceFinding>> $result */
        $result = [];
        foreach ($byUserDate as $uid => $days) {
            $findings = [];
            foreach ($days as $date => $agg) {
                // 1. Vergessene Geht-Stempelung: Anwesenheit über den Tag hinaus offen.
                if ($ruleOn('plausibility_missing_checkout') && $agg['open'] && $date < $today && $agg['open_start'] !== null) {
                    $findings[] = new AttendanceComplianceFinding(
                        userId: $uid,
                        date: $date,
                        kind: self::KIND_MISSING_CHECKOUT,
                        severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                        value: max(0, (int) $agg['open_start']->diffInMinutes($agg['open_start']->endOfDay(), false)),
                        threshold: 0,
                    );
                }

                if ($agg['gross'] <= 0) {
                    continue;
                }

                // 2. Stempelung trotz genehmigter ganztägiger Abwesenheit (hat
                //    Vorrang vor dem Frei-Tag-Befund — nicht doppelt melden).
                if (isset($absenceSet[$uid . '|' . $date])) {
                    if ($ruleOn('plausibility_absence_conflict')) {
                        $findings[] = new AttendanceComplianceFinding(
                            userId: $uid,
                            date: $date,
                            kind: self::KIND_ABSENCE_STAMP,
                            severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                            value: $agg['gross'],
                            threshold: 0,
                        );
                    }
                } elseif ($ruleOn('plausibility_free_day') && ! isset($shiftSet[$uid . '|' . $date])) {
                    // 3. Stempelung an planmäßig freiem Tag: kein Dienst geplant
                    //    UND das Arbeitszeitmodell sieht den Wochentag nicht vor.
                    //    Ohne Arbeitszeitmodell ist der Tag nicht bewertbar.
                    $schedule = $this->scheduleFor($schedulesByUser->get($uid, collect()), $date);
                    if ($schedule !== null && ! $schedule->appliesOnWeekday((int) CarbonImmutable::parse($date)->isoWeekday())) {
                        $findings[] = new AttendanceComplianceFinding(
                            userId: $uid,
                            date: $date,
                            kind: self::KIND_FREE_DAY_STAMP,
                            severity: AttendanceComplianceFinding::SEVERITY_WARNING,
                            value: $agg['gross'],
                            threshold: 0,
                        );
                    }
                }

                // 4. Rahmenzeit-Überschreitung jenseits der Bagatellgrenze.
                $outside = $agg['before'] + $agg['after'];
                if ($ruleOn('plausibility_frame_time') && $outside > $tolerance) {
                    $findings[] = new AttendanceComplianceFinding(
                        userId: $uid,
                        date: $date,
                        kind: self::KIND_FRAME_TIME,
                        severity: AttendanceComplianceFinding::SEVERITY_WARNING,
                        value: $outside,
                        threshold: $tolerance,
                    );
                }
            }

            if ($findings !== []) {
                usort($findings, static fn (AttendanceComplianceFinding $a, AttendanceComplianceFinding $b): int => [$a->date, $a->kind] <=> [$b->date, $b->kind]);
                $result[$uid] = $findings;
            }
        }

        return $result;
    }

    /** @param Collection<int, WorkSchedule> $schedules */
    private function scheduleFor(Collection $schedules, string $date): ?WorkSchedule {
        foreach ($schedules->reverse() as $s) {
            $fromOk = $s->valid_from->format('Y-m-d') <= $date;
            $toOk = $s->valid_to === null || $s->valid_to->format('Y-m-d') >= $date;
            if ($fromOk && $toOk) {
                return $s;
            }
        }

        return null;
    }
}
