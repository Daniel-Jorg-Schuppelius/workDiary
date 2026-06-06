<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarbonFmt.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Logik hinter den Carbon-Anzeige-Macros (orgTz/fdate/fdatetime/ftime).
 * Ausgelagert als typisierte Helfer, damit die Macro-Registrierung in
 * AppServiceProvider schlank und statisch analysierbar bleibt.
 */
final class CarbonFmt {
    /** In die aktive Anzeige-Zeitzone umrechnen. */
    public static function orgTz(CarbonInterface $dt): CarbonInterface {
        return $dt->copy()->setTimezone(Tz::current());
    }

    /** Reines Datum im konfigurierten Format (ohne TZ-Umrechnung). */
    public static function fdate(CarbonInterface $dt): string {
        return $dt->translatedFormat(Formats::date());
    }

    /** Datum + Uhrzeit in Anzeige-Zeitzone + konfiguriertem Format. */
    public static function fdatetime(CarbonInterface $dt): string {
        return $dt->copy()->setTimezone(Tz::current())->translatedFormat(Formats::dateTime());
    }

    /** Uhrzeit in Anzeige-Zeitzone + konfiguriertem Format. */
    public static function ftime(CarbonInterface $dt): string {
        return $dt->copy()->setTimezone(Tz::current())->translatedFormat(Formats::time());
    }
}
