<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoverageService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\{CoverageRequirement, DutyPlan, ScheduledShift, ShiftType};
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonPeriod};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
class CoverageService {
    /** Status, die als "tatsächlich besetzt" zählen. */
    private const ACTUAL_STATUSES = [
        ScheduledShiftStatus::Published->value,
        ScheduledShiftStatus::Confirmed->value,
    ];

    /**
     * @return array<string, array<int, array{min:int, max:?int, qualification_ids:array<int,int>, qualification_minima:array<int,int>}>>
     *                                                                                                                                     keyed by date (Y-m-d) → shift_type_id
     */
    public function requirementsFor(DutyPlan $dutyPlan, ?CarbonPeriod $period = null): array {
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
                        'qualification_minima' => [],
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
                        'ideal' => $req->ideal_staff,
                        'qualification_ids' => $req->required_qualification_ids ?? [],
                        // MVP-530: „davon mindestens N mit Qualifikation X".
                        'qualification_minima' => $req->qualificationMinima(),
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
    public function actualStaffing(DutyPlan $dutyPlan, ?CarbonPeriod $period = null): array {
        $start = $period?->getStartDate();
        $end = $period?->getEndDate();
        $from = $start !== null ? CarbonImmutable::instance($start) : CarbonImmutable::instance($dutyPlan->from_date);
        $to = $end !== null ? CarbonImmutable::instance($end) : CarbonImmutable::instance($dutyPlan->to_date);

        $rows = ScheduledShift::query()
            ->where('duty_plan_id', $dutyPlan->id)
            ->whereIn('status', self::ACTUAL_STATUSES)
            ->whereNotNull('shift_type_id')
            ->whereBetween('date', DateRange::days($from, $to))
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
    public function gaps(DutyPlan $dutyPlan, ?CarbonPeriod $period = null): array {
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
     * Cell-Status für Heatmap-Färbung. „tight" = gerade noch ausreichend
     * (exakt am Minimum bzw. unter der Ideal-Besetzung, Q1-Gelb-Zone).
     *
     * @return 'ok'|'tight'|'under'|'over'|'idle'
     */
    public function cellStatus(int $actual, ?int $min, ?int $max, ?int $ideal = null): string {
        if ($min === null || $min === 0) {
            return $actual > 0 ? 'ok' : 'idle';
        }
        if ($actual < $min) {
            return 'under';
        }
        if ($max !== null && $actual > $max) {
            return 'over';
        }
        if ($ideal !== null && $ideal > $min) {
            return $actual < $ideal ? 'tight' : 'ok';
        }

        // Exakt am Minimum = „gerade noch" — außer die Vorgabe ist ein
        // Fixwert (min == max), dann ist das Minimum zugleich das Soll.
        return ($actual === $min && ($max === null || $max > $min)) ? 'tight' : 'ok';
    }

    /**
     * MVP-530: Ist-Besetzung je Qualifikation — wie viele der eingeteilten
     * Personen halten die Qualifikation am jeweiligen Tag (Pivot-Gültigkeit
     * wie {@see \App\Services\Schedule\QualificationGate}). Zählt Personen
     * (distinct), nicht Schichten.
     *
     * @param  array<int, int>  $qualificationIds
     * @return array<string, array<int, array<int, int>>> date → shift_type_id → qualification_id → Anzahl
     */
    public function actualQualifiedStaffing(DutyPlan $dutyPlan, array $qualificationIds, ?CarbonPeriod $period = null): array {
        $qualificationIds = array_values(array_unique(array_map('intval', $qualificationIds)));
        if ($qualificationIds === []) {
            return [];
        }

        $start = $period?->getStartDate();
        $end = $period?->getEndDate();
        $from = $start !== null ? CarbonImmutable::instance($start) : CarbonImmutable::instance($dutyPlan->from_date);
        $to = $end !== null ? CarbonImmutable::instance($end) : CarbonImmutable::instance($dutyPlan->to_date);

        $shifts = ScheduledShift::query()
            ->where('duty_plan_id', $dutyPlan->id)
            ->whereIn('status', self::ACTUAL_STATUSES)
            ->whereNotNull('shift_type_id')
            ->whereNotNull('user_id')
            ->whereBetween('date', DateRange::days($from, $to))
            ->get(['id', 'date', 'shift_type_id', 'user_id']);
        if ($shifts->isEmpty()) {
            return [];
        }

        // Pivot-Zeilen der beteiligten Personen (Gültigkeit wird pro Tag geprüft).
        $pivotRows = DB::table('user_qualifications')
            ->whereIn('user_id', $shifts->pluck('user_id')->unique()->all())
            ->whereIn('qualification_id', $qualificationIds)
            ->get(['user_id', 'qualification_id', 'valid_from', 'valid_until'])
            ->groupBy('user_id');

        $out = [];
        $seen = [];
        foreach ($shifts as $shift) {
            /** @var ScheduledShift $shift */
            $date = $shift->date->format('Y-m-d');
            /** @var Collection<int, \stdClass> $rows */
            $rows = $pivotRows->get($shift->user_id, collect());
            foreach ($rows as $row) {
                if (! $this->pivotValidOn($row, $date)) {
                    continue;
                }
                // Eine Person zählt je Tag/Typ/Qualifikation nur einmal.
                $key = $date . '|' . $shift->shift_type_id . '|' . $row->qualification_id . '|' . $shift->user_id;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[$date][(int) $shift->shift_type_id][(int) $row->qualification_id] =
                    ($out[$date][(int) $shift->shift_type_id][(int) $row->qualification_id] ?? 0) + 1;
            }
        }

        return $out;
    }

    /**
     * MVP-530: Unterdeckungen der Qualifikations-Minima („mindestens 2
     * Examinierte in der Frühschicht") über den Planzeitraum.
     *
     * @return list<array{date:string, shift_type_id:int, qualification_id:int, required:int, actual:int}>
     */
    public function qualificationGaps(DutyPlan $dutyPlan, ?CarbonPeriod $period = null): array {
        $req = $this->requirementsFor($dutyPlan, $period);

        $qualIds = [];
        foreach ($req as $perType) {
            foreach ($perType as $cfg) {
                foreach (array_keys($cfg['qualification_minima']) as $qid) {
                    $qualIds[$qid] = $qid;
                }
            }
        }
        if ($qualIds === []) {
            return [];
        }

        $actual = $this->actualQualifiedStaffing($dutyPlan, array_values($qualIds), $period);

        $gaps = [];
        foreach ($req as $date => $perType) {
            foreach ($perType as $stid => $cfg) {
                foreach ($cfg['qualification_minima'] as $qid => $needed) {
                    $have = $actual[$date][$stid][$qid] ?? 0;
                    if ($have < $needed) {
                        $gaps[] = [
                            'date' => $date,
                            'shift_type_id' => (int) $stid,
                            'qualification_id' => (int) $qid,
                            'required' => (int) $needed,
                            'actual' => $have,
                        ];
                    }
                }
            }
        }

        return $gaps;
    }

    /** Pivot-Gültigkeit am Stichtag (Y-m-d-Stringvergleich reicht bei Datumsspalten). */
    private function pivotValidOn(\stdClass $row, string $date): bool {
        $from = is_string($row->valid_from) && $row->valid_from !== '' ? substr($row->valid_from, 0, 10) : null;
        if ($from !== null && $from > $date) {
            return false;
        }
        $until = is_string($row->valid_until) && $row->valid_until !== '' ? substr($row->valid_until, 0, 10) : null;

        return $until === null || $until >= $date;
    }

    /**
     * Lädt alle ShiftTypes, die in Plan oder Anforderungen vorkommen.
     * Praktisch für die Spalten der Coverage-Matrix-View.
     *
     * @return Collection<int, ShiftType>
     */
    public function relevantShiftTypes(DutyPlan $dutyPlan): Collection {
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
