<?php
/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DateFacade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Toolkit;

use CommonToolkit\Enums\DateTimeFormat;
use CommonToolkit\Enums\Weekday;
use CommonToolkit\Helper\Data\DateHelper;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Wrapper um CommonToolkit\DateHelper.
 *
 * App-Code soll für nicht-Eloquent Datumsoperationen diese Facade nutzen
 * (statt direktem Carbon-Code mit unklarem Verhalten an Format-Grenzen).
 */
final class DateFacade {
    public static function tryParse(string $value): ?DateTimeImmutable {
        return DateHelper::parseFlexible($value);
    }

    public static function isValid(string $value, DateTimeFormat $preferredFormat = DateTimeFormat::DE): bool {
        $format = null;

        return DateHelper::isDate($value, $format, $preferredFormat);
    }

    public static function isWeekend(DateTimeInterface $date): bool {
        return DateHelper::isWeekend($date);
    }

    public static function diffInDays(DateTimeInterface $start, DateTimeInterface $end): int {
        return DateHelper::diffInDays($start, $end);
    }

    public static function isToday(DateTimeInterface $date): bool {
        return DateHelper::isToday($date);
    }

    public static function isPast(DateTimeInterface $date): bool {
        return DateHelper::isPast($date);
    }

    public static function isFuture(DateTimeInterface $date): bool {
        return DateHelper::isFuture($date);
    }

    /**
     * Liefert lokalisierte Wochentags-Abkürzungen (Mo-So).
     *
     * @return list<string>
     */
    public static function weekdayAbbreviations(string $locale = 'de', bool $long = false): array {
        $days = [
            Weekday::MONDAY,
            Weekday::TUESDAY,
            Weekday::WEDNESDAY,
            Weekday::THURSDAY,
            Weekday::FRIDAY,
            Weekday::SATURDAY,
            Weekday::SUNDAY,
        ];

        return array_map(static function (Weekday $day) use ($locale, $long): string {
            $name = $day->getName($locale);

            return $long ? $name : mb_substr($name, 0, 2);
        }, $days);
    }
}
