<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftRotationRoller.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\Vacation\VacationStatus;
use App\Models\{OnCallShift, Organization, ScheduledShift, ShiftRotationAssignment, SickLeave, Vacation};
use Carbon\CarbonImmutable;

/**
 * Schreibt Rollpläne fort (MVP-522): erzeugt aus aktiven Rotations-
 * Zuweisungen Draft-Dienste für ein Zukunftsfenster. Idempotent —
 * vorhandene (auch manuell geplante) Dienste des Tages sowie genehmigte
 * Abwesenheiten gewinnen; der Rollplan füllt nur Lücken. Damit entspricht
 * die Draft-Ebene der Q1-„Vorplanung", die von der amtlichen Planung
 * überschrieben werden kann.
 */
final class ShiftRotationRoller {
    /**
     * @return array{created: int, skipped: int}
     */
    public function rollForward(Organization $organization, CarbonImmutable $from, int $weeks = 4): array {
        $weeks = max(1, min(26, $weeks));
        $from = $from->startOfDay();
        $to = $from->addWeeks($weeks)->subDay();

        $assignments = ShiftRotationAssignment::query()
            ->where('organization_id', $organization->getKey())
            ->with(['rotation.entries.shiftType'])
            ->get()
            ->filter(fn (ShiftRotationAssignment $a): bool => (bool) $a->rotation?->is_active);

        if ($assignments->isEmpty()) {
            return ['created' => 0, 'skipped' => 0];
        }

        $userIds = $assignments->pluck('user_id')->unique()->values()->all();

        // Bestehende Dienste im Fenster (jeder Status außer storniert blockiert den Slot).
        $occupied = [];
        ScheduledShift::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->get(['user_id', 'date'])
            ->each(function (ScheduledShift $s) use (&$occupied): void {
                $occupied[$s->user_id . '|' . $s->date->toDateString()] = true;
            });

        // Genehmigte Abwesenheiten im Fenster.
        $absent = [];
        $mark = function (int $uid, string $start, string $end) use (&$absent, $from, $to): void {
            $cursor = CarbonImmutable::parse($start);
            $cursor = $cursor->lessThan($from) ? $from : $cursor;
            $limit = CarbonImmutable::parse($end);
            $limit = $limit->greaterThan($to) ? $to : $limit;
            while ($cursor->lessThanOrEqualTo($limit)) {
                $absent[$uid . '|' . $cursor->toDateString()] = true;
                $cursor = $cursor->addDay();
            }
        };
        Vacation::query()
            ->whereIn('user_id', $userIds)
            ->where('status', VacationStatus::Approved->value)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get(['user_id', 'start_date', 'end_date'])
            ->each(fn (Vacation $v) => $mark((int) $v->user_id, $v->start_date->toDateString(), $v->end_date->toDateString()));
        SickLeave::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get(['user_id', 'start_date', 'end_date'])
            ->each(fn (SickLeave $s) => $mark((int) $s->user_id, $s->start_date->toDateString(), $s->end_date->toDateString()));

        $created = 0;
        $skipped = 0;

        foreach ($assignments as $assignment) {
            $rotation = $assignment->rotation;
            if ($rotation === null || $rotation->weeks_count < 1) {
                continue;
            }

            // Slot-Matrix: weekIndex|isoWeekday → Entry.
            $slots = [];
            foreach ($rotation->entries as $entry) {
                $slots[$entry->week_index . '|' . $entry->iso_weekday] = $entry;
            }
            if ($slots === []) {
                continue;
            }

            $anchorMonday = CarbonImmutable::parse($assignment->anchor_date->toDateString())->startOfWeek();
            $uid = (int) $assignment->user_id;

            for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
                if ($assignment->valid_from !== null && $day->lessThan(CarbonImmutable::parse($assignment->valid_from->toDateString()))) {
                    continue;
                }
                if ($assignment->valid_until !== null && $day->greaterThan(CarbonImmutable::parse($assignment->valid_until->toDateString()))) {
                    continue;
                }

                $weekDiff = (int) floor(((int) $anchorMonday->diffInDays($day->startOfWeek(), false)) / 7);
                $weekIndex = (($weekDiff % $rotation->weeks_count) + $rotation->weeks_count) % $rotation->weeks_count;

                $entry = $slots[$weekIndex . '|' . $day->isoWeekday()] ?? null;
                if ($entry === null) {
                    continue; // dienstfreier Tag laut Rhythmus
                }

                $key = $uid . '|' . $day->toDateString();
                if (isset($occupied[$key]) || isset($absent[$key])) {
                    $skipped++;

                    continue;
                }

                $shiftType = $entry->shiftType;
                ScheduledShift::query()->create([
                    'organization_id' => $organization->getKey(),
                    'user_id' => $uid,
                    'shift_type_id' => $entry->shift_type_id,
                    'date' => $day->toDateString(),
                    'start_time' => $shiftType?->default_start_time,
                    'end_time' => $shiftType?->default_end_time,
                    'status' => ScheduledShiftStatus::Draft->value,
                ]);
                $occupied[$key] = true;
                $created++;

                // Kombi-Dienst (Q1 „Dienst mit Rufbereitschaft"): der Schichttyp
                // trägt eine anschließende Rufbereitschaftszeit → OnCallShift.
                if ($shiftType?->on_call_start_time !== null && $shiftType->on_call_end_time !== null) {
                    $this->createOnCallShift($organization, $uid, $day, (string) $shiftType->on_call_start_time, (string) $shiftType->on_call_end_time);
                }
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Rufbereitschaft eines Kombi-Diensts anlegen (über Mitternacht → Folgetag);
     * idempotent über ein exaktes Zeitfenster-Match.
     */
    private function createOnCallShift(Organization $organization, int $userId, CarbonImmutable $day, string $startTime, string $endTime): void {
        $start = $day->setTimeFromTimeString($startTime);
        $end = $day->setTimeFromTimeString($endTime);
        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        $exists = OnCallShift::query()
            ->where('user_id', $userId)
            ->where('start_at', $start->toDateTimeString())
            ->where('end_at', $end->toDateTimeString())
            ->exists();
        if ($exists) {
            return;
        }

        OnCallShift::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $userId,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
            'note' => (string) __('Kombi-Dienst: Rufbereitschaft aus Rollplan.'),
        ]);
    }
}
