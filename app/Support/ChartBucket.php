<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChartBucket.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Zeitreihen-Granularität aus dem globalen Header-Zeitraum
 * ({@see \App\Services\UI\DateRangeContext} liefert `unit`). Damit reagieren
 * Auswertungs-Diagramme einheitlich auf die Header-Zeitangabe:
 * Tag → Stunden, Woche → Tage, Monat/Quartal → Wochen, Jahr → Monate,
 * mehrjährig → Quartale.
 */
final class ChartBucket {
    /**
     * Granularität für die gewählte Zeitraum-Einheit; bei „custom" aus der
     * Spannweite abgeleitet.
     *
     * @return 'hour'|'day'|'week'|'month'|'quarter'
     */
    public static function granularity(string $unit, CarbonImmutable $from, CarbonImmutable $to): string {
        return match ($unit) {
            'day' => 'hour',
            'week' => 'day',
            'month', 'quarter' => 'week',
            'year' => 'month',
            default => self::fromSpan($from, $to),
        };
    }

    /** @return 'hour'|'day'|'week'|'month'|'quarter' */
    private static function fromSpan(CarbonImmutable $from, CarbonImmutable $to): string {
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;

        return match (true) {
            $days <= 1 => 'hour',
            $days <= 14 => 'day',
            $days <= 92 => 'week',
            $days <= 730 => 'month',
            default => 'quarter',
        };
    }

    /**
     * Bucket-Schlüssel (sortierstabil) und Anzeigelabel für einen Tag in der
     * jeweiligen Granularität. Für 'hour' ist die Tagesebene nicht sinnvoll —
     * Aufrufer bucketn Stunden separat.
     *
     * @param  'day'|'week'|'month'|'quarter'  $granularity
     * @return array{0: string, 1: string} [$key, $label]
     */
    public static function keyLabel(string $granularity, CarbonImmutable $day): array {
        return match ($granularity) {
            'week' => ['W' . $day->isoWeekYear . sprintf('%02d', $day->isoWeek), sprintf('KW %02d', $day->isoWeek)],
            'month' => [$day->format('Y-m'), $day->format('m.Y')],
            'quarter' => [$day->format('Y') . 'Q' . $day->quarter, 'Q' . $day->quarter . ' ' . $day->format('Y')],
            default => [$day->toDateString(), $day->format('d.m.')],
        };
    }
}
