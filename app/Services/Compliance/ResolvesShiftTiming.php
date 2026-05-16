<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesShiftTiming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\ScheduledShift;
use Carbon\CarbonImmutable;

/**
 * Helper für Compliance-Regeln: Datum/Zeit-Berechnung einer Schicht.
 */
trait ResolvesShiftTiming {
    /** Wirksame Startzeit (eigene oder ShiftType-Default), Format H:i:s oder H:i. */
    protected function effectiveStart(ScheduledShift $shift): ?string {
        return $shift->start_time ?? $shift->shiftType?->default_start_time;
    }

    protected function effectiveEnd(ScheduledShift $shift): ?string {
        return $shift->end_time ?? $shift->shiftType?->default_end_time;
    }

    /**
     * Liefert [startUtc, endUtc] als CarbonImmutable. Behandelt Über-Mitternacht.
     * Gibt null zurück, falls keine Zeit auflösbar ist.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    protected function resolveInterval(ScheduledShift $shift): ?array {
        $start = $this->effectiveStart($shift);
        $end = $this->effectiveEnd($shift);
        if ($start === null || $end === null) {
            return null;
        }

        $date = CarbonImmutable::parse($shift->date->format('Y-m-d'));
        $s = CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $start);
        $e = CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $end);
        if ($e->lessThanOrEqualTo($s)) {
            $e = $e->addDay();
        }

        return [$s, $e];
    }

    protected function durationHours(ScheduledShift $shift): float {
        $iv = $this->resolveInterval($shift);
        if ($iv === null) {
            return 0.0;
        }

        return abs($iv[0]->diffInMinutes($iv[1])) / 60.0;
    }
}
