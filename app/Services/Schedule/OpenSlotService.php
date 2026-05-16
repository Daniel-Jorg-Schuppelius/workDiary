<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenSlotService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\DutyPlan;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Services\CoverageService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Berechnet pro Datum + Schichttyp die Anzahl fehlender Soll-Slots
 * (Soll aus aktiven `DutyPlan`s, Ist aus geladenen Schichten außer `cancelled`).
 */
class OpenSlotService {
    public function __construct(private readonly CoverageService $coverage) {
    }

    /**
     * @param  Collection<int, ScheduledShift>  $shifts
     * @return array<string, list<array{shift_type_id:int, missing:int, name:string, abbreviation:string, color:string}>>
     */
    public function compute(CarbonImmutable $from, CarbonImmutable $to, Collection $shifts): array {
        $plans = DutyPlan::query()
            ->inPeriod($from->toDateString(), $to->toDateString())
            ->get();

        if ($plans->isEmpty()) {
            return [];
        }

        $period = CarbonPeriod::create($from, $to);
        $minByDate = $this->aggregateRequirements($plans, $period);

        if ($minByDate === []) {
            return [];
        }

        $actualByDate = $this->aggregateActualShifts($shifts);
        $shiftTypes = $this->loadShiftTypes($minByDate);

        return $this->buildResult($minByDate, $actualByDate, $shiftTypes);
    }

    /**
     * @param  Collection<int, DutyPlan>  $plans
     * @return array<string, array<int, int>>
     */
    private function aggregateRequirements(Collection $plans, CarbonPeriod $period): array {
        $minByDate = [];
        foreach ($plans as $plan) {
            $reqs = $this->coverage->requirementsFor($plan, $period);
            foreach ($reqs as $date => $perType) {
                foreach ($perType as $stid => $info) {
                    $minByDate[$date][(int) $stid] = ($minByDate[$date][(int) $stid] ?? 0) + (int) $info['min'];
                }
            }
        }

        return $minByDate;
    }

    /**
     * @param  Collection<int, ScheduledShift>  $shifts
     * @return array<string, array<int, int>>
     */
    private function aggregateActualShifts(Collection $shifts): array {
        $actualByDate = [];
        foreach ($shifts as $shift) {
            if ($shift->status === ScheduledShift::STATUS_CANCELLED) {
                continue;
            }
            if ($shift->shift_type_id === null) {
                continue;
            }
            $date = $shift->date->toDateString();
            $stid = (int) $shift->shift_type_id;
            $actualByDate[$date][$stid] = ($actualByDate[$date][$stid] ?? 0) + 1;
        }

        return $actualByDate;
    }

    /**
     * @param  array<string, array<int, int>>  $minByDate
     * @return Collection<int, ShiftType>
     */
    private function loadShiftTypes(array $minByDate): Collection {
        $stIds = [];
        foreach ($minByDate as $perType) {
            foreach ($perType as $stid => $_) {
                $stIds[$stid] = true;
            }
        }

        return ShiftType::query()->whereIn('id', array_keys($stIds))->get()->keyBy('id');
    }

    /**
     * @param  array<string, array<int, int>>  $minByDate
     * @param  array<string, array<int, int>>  $actualByDate
     * @param  Collection<int, ShiftType>  $shiftTypes
     * @return array<string, list<array{shift_type_id:int, missing:int, name:string, abbreviation:string, color:string}>>
     */
    private function buildResult(array $minByDate, array $actualByDate, Collection $shiftTypes): array {
        $out = [];
        foreach ($minByDate as $date => $perType) {
            foreach ($perType as $stid => $min) {
                $actual = $actualByDate[$date][$stid] ?? 0;
                $missing = $min - $actual;
                if ($missing <= 0) {
                    continue;
                }
                $type = $shiftTypes->get($stid);
                if (! $type) {
                    continue;
                }
                $out[$date][] = [
                    'shift_type_id' => (int) $stid,
                    'missing' => $missing,
                    'name' => (string) $type->name,
                    'abbreviation' => (string) ($type->abbreviation ?? '?'),
                    'color' => (string) ($type->color ?? '#6b7280'),
                ];
            }
        }

        return $out;
    }
}
