<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CapacityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\{ManufacturingOrder, WorkCenter};
use Illuminate\Support\Carbon;

/**
 * Kapazitäts-/Maschinenbelegung (Feature 047/048, E7). Weist Fertigungsaufträge
 * einem Arbeitsplatz mit geplanter Belegungsdauer zu und berechnet die Tageslast
 * (geplante Minuten inkl. Rüstzeit je Auftrag gegen die Tageskapazität).
 */
class CapacityService {
    public function assign(ManufacturingOrder $order, WorkCenter $workCenter, int $plannedMinutes, ?Carbon $day = null): ManufacturingOrder {
        $order->forceFill([
            'work_center_id' => $workCenter->id,
            'planned_minutes' => max(0, $plannedMinutes),
            'planned_start' => $day ?? $order->planned_start,
        ])->save();

        return $order;
    }

    /**
     * Tageslast eines Arbeitsplatzes: geplante Minuten (inkl. Rüstzeit je Auftrag)
     * gegen die Tageskapazität.
     *
     * @return array{capacity: int, planned: int, free: int, utilization: float, overloaded: bool}
     */
    public function load(WorkCenter $workCenter, Carbon $day): array {
        $orders = ManufacturingOrder::query()
            ->where('work_center_id', $workCenter->id)
            ->whereDate('planned_start', $day->toDateString())
            ->get();

        $planned = 0;
        foreach ($orders as $order) {
            $planned += (int) ($order->planned_minutes ?? 0) + $workCenter->setup_minutes;
        }

        $capacity = $workCenter->capacity_minutes;
        $utilization = $capacity > 0 ? round($planned / $capacity, 4) : 0.0;

        return [
            'capacity' => $capacity,
            'planned' => $planned,
            'free' => $capacity - $planned,
            'utilization' => $utilization,
            'overloaded' => $planned > $capacity,
        ];
    }

    /**
     * Aggregierte Belegung über einen Zeitraum (Header-Zeitraum): summiert die
     * geplanten Minuten aller Aufträge mit Plantermin im Bereich gegen die
     * Periodenkapazität (Tageskapazität × Anzahl Tage).
     *
     * @return array{capacity:int, planned:int, free:int, utilization:float, overloaded:bool, days:int}
     */
    public function loadRange(WorkCenter $workCenter, Carbon $from, Carbon $to): array {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $orders = ManufacturingOrder::query()
            ->where('work_center_id', $workCenter->id)
            ->whereBetween('planned_start', [$start, $end])
            ->get();

        $planned = 0;
        foreach ($orders as $order) {
            $planned += (int) ($order->planned_minutes ?? 0) + $workCenter->setup_minutes;
        }

        $days = (int) $start->diffInDays($end) + 1;
        $capacity = $workCenter->capacity_minutes * $days;
        $utilization = $capacity > 0 ? round($planned / $capacity, 4) : 0.0;

        return [
            'capacity' => $capacity,
            'planned' => $planned,
            'free' => $capacity - $planned,
            'utilization' => $utilization,
            'overloaded' => $planned > $capacity,
            'days' => $days,
        ];
    }
}
