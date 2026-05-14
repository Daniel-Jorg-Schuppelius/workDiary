<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CoverageRequirement;
use App\Models\DutyPlan;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Berechnet Soll-/Ist-Besetzung pro Tag und Schichttyp für einen DutyPlan.
 *
 * Soll-Auflösung pro Tag + ShiftType (höhere Priorität gewinnt):
 *   3. CoverageRequirement.specific_date == Datum (im Plan oder org-weit)
 *   2. CoverageRequirement.weekday      == Wochentag (im Plan oder org-weit)
 *   1. DutyPlan.min_staff (Plan-Default, gilt für alle Schichttypen die im Plan vorkommen)
 *
 * Ist-Werte zählen nur Schichten mit Status `published` oder `confirmed`
 * (Entwürfe / abgesagte Schichten werden ignoriert).
 */
class CoverageService
{
    /** Status, die als "tatsächlich besetzt" zählen. */
    private const ACTUAL_STATUSES = [
        ScheduledShift::STATUS_PUBLISHED,
        ScheduledShift::STATUS_CONFIRMED,
    ];

    /**
     * @return array<string, array<int, array{min:int, max:?int, qualification_ids:array<int,int>}>>
     *                                                                                               keyed by date (Y-m-d) → shift_type_id → ['min','max','qualification_ids']
     */
    public function requirementsFor(DutyPlan $dutyPlan, ?CarbonPeriod $period = null): array
    {
        $period ??= CarbonPeriod::create($dutyPlan->from_date, $dutyPlan->to_date);

        // Eine Query reicht: alle Anforderungen für Plan oder org-weit.
        $reqs = CoverageRequirement::query()
            ->forPlan($dutyPlan->id)
            ->get();

        // Alle Schichttypen, die im Plan tatsächlich auftauchen (für min_staff-Fallback).
        $planShiftTypeIds = $dutyPlan->shifts()
            ->whereNotNull('shift_type_id')
            ->distinct()
            ->pluck('shift_type_id')
            ->all();

        $out = [];
        foreach ($period as $day) {
            /** @var \DateTimeInterface $day */
            $dateStr = $day->format('Y-m-d');
            $perType = [];

            // Plan-Default: jeder genutzte Schichttyp muss min_staff erfüllen.
            if ($dutyPlan->min_staff > 0) {
                foreach ($planShiftTypeIds as $stid) {
                    $perType[(int) $stid] = [
                        'min' => $dutyPlan->min_staff,
                        'max' => null,
                        'qualification_ids' => [],
                        '_priority' => 1,
                    ];
                }
            }

            // Anforderungen für diesen Tag, Priorität 2/3.
            foreach ($reqs as $req) {
                /** @var CoverageRequirement $req */
                if (! $req->appliesToDate($day)) {
                    continue;
                }
                $stid = $req->shift_type_id;
                $priority = $req->priority();

                if (! isset($perType[$stid]) || $perType[$stid]['_priority'] < $priority) {
                    $perType[$stid] = [
                        'min' => $req->min_staff,
                        'max' => $req->max_staff,
                        'qualification_ids' => $req->required_qualification_ids ?? [],
                        '_priority' => $priority,
                    ];
                }
            }

            // _priority entfernen
            foreach ($perType as &$row) {
                unset($row['_priority']);
            }
            unset($row);

            $out[$dateStr] = $perType;
        }

        return $out;
    }

    /**
     * @return array<string, array<int, int>> date → shift_type_id → count
     */
    public function actualStaffing(DutyPlan $dutyPlan, ?CarbonPeriod $period = null): array
    {
        $from = $period ? CarbonImmutable::instance($period->getStartDate()) : CarbonImmutable::instance($dutyPlan->from_date);
        $to = $period ? CarbonImmutable::instance($period->getEndDate()) : CarbonImmutable::instance($dutyPlan->to_date);

        $rows = ScheduledShift::query()
            ->where('duty_plan_id', $dutyPlan->id)
            ->whereIn('status', self::ACTUAL_STATUSES)
            ->whereNotNull('shift_type_id')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->toBase()
            ->selectRaw('date, shift_type_id, COUNT(*) as cnt')
            ->groupBy('date', 'shift_type_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            /** @var \stdClass $row */
            $date = (string) $row->date;
            $out[substr($date, 0, 10)][(int) $row->shift_type_id] = (int) $row->cnt;
        }

        return $out;
    }

    /**
     * Liste unter-/überbesetzter Slots.
     *
     * @return list<array{date:string, shift_type_id:int, min:int, max:?int, actual:int, severity:'under'|'over'}>
     */
    public function gaps(DutyPlan $dutyPlan, ?CarbonPeriod $period = null): array
    {
        $period ??= CarbonPeriod::create($dutyPlan->from_date, $dutyPlan->to_date);
        $req = $this->requirementsFor($dutyPlan, $period);
        $actual = $this->actualStaffing($dutyPlan, $period);

        $gaps = [];
        foreach ($req as $date => $perType) {
            foreach ($perType as $stid => $cfg) {
                $a = $actual[$date][$stid] ?? 0;
                if ($a < $cfg['min']) {
                    $gaps[] = [
                        'date' => $date,
                        'shift_type_id' => (int) $stid,
                        'min' => (int) $cfg['min'],
                        'max' => $cfg['max'],
                        'actual' => $a,
                        'severity' => 'under',
                    ];
                } elseif ($cfg['max'] !== null && $a > $cfg['max']) {
                    $gaps[] = [
                        'date' => $date,
                        'shift_type_id' => (int) $stid,
                        'min' => (int) $cfg['min'],
                        'max' => $cfg['max'],
                        'actual' => $a,
                        'severity' => 'over',
                    ];
                }
            }
        }

        return $gaps;
    }

    /**
     * Cell-Status für Heatmap-Färbung.
     *
     * @return 'ok'|'under'|'over'|'idle'
     */
    public function cellStatus(int $actual, ?int $min, ?int $max): string
    {
        if ($min === null || $min === 0) {
            return $actual > 0 ? 'ok' : 'idle';
        }
        if ($actual < $min) {
            return 'under';
        }
        if ($max !== null && $actual > $max) {
            return 'over';
        }

        return 'ok';
    }

    /**
     * Lädt alle ShiftTypes, die in Plan oder Anforderungen vorkommen.
     * Praktisch für die Spalten der Coverage-Matrix-View.
     *
     * @return Collection<int, ShiftType>
     */
    public function relevantShiftTypes(DutyPlan $dutyPlan): Collection
    {
        $fromShifts = $dutyPlan->shifts()->whereNotNull('shift_type_id')->pluck('shift_type_id');
        $fromReqs = CoverageRequirement::query()
            ->forPlan($dutyPlan->id)
            ->pluck('shift_type_id');

        $ids = $fromShifts->merge($fromReqs)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        /** @var Collection<int, ShiftType> $types */
        $types = ShiftType::query()->whereIn('id', $ids)->orderBy('name')->get();

        return $types;
    }
}
