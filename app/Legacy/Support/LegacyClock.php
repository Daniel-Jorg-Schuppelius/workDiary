<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyClock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Support;

use App\Support\{CarbonFmt, Tz};
use Carbon\{Carbon, CarbonInterface};

/**
 * Uhr des Altsystems: es speichert Ortszeit ohne Zone (Zone der Installation,
 * `app.display_timezone`). Eloquent liest solche Werte als Wandzeit in der
 * App-Zeitzone; {@see now()} hat dieselbe Form, damit Vergleiche in PHP und
 * SQL stimmen. Angezeigt wird ohne Umrechnung, übernommen ins neue System mit
 * Umrechnung nach UTC ({@see toUtc()}).
 */
final class LegacyClock {
    public static function zone(): string {
        $zone = (string) config('app.display_timezone', Tz::FALLBACK);

        return Tz::isValid($zone) ? $zone : Tz::FALLBACK;
    }

    public static function now(): Carbon {
        return Carbon::parse(Carbon::now(self::zone())->format('Y-m-d H:i:s'));
    }

    public static function today(): Carbon {
        return self::now()->startOfDay();
    }

    public static function nowString(): string {
        return self::now()->format('Y-m-d H:i:s');
    }

    /** Datum und Uhrzeit eines Altsystem-Werts, unverschoben. */
    public static function format(?CarbonInterface $value): ?string {
        return $value === null ? null : CarbonFmt::fdatetime($value, $value->getTimezone()->getName());
    }

    /** Wandzeit des Altsystems (`Y-m-d[ H:i:s]`) als UTC-Wert für das neue System. */
    public static function toUtc(string $wallClock): string {
        return Carbon::parse($wallClock, self::zone())->utc()->format('Y-m-d H:i:s');
    }
}
