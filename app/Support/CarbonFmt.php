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
        return self::format($dt, Formats::date());
    }

    /**
     * Formate ohne Namen (Monat, Wochentag, am/pm) brauchen keine Übersetzung:
     * direkt formatieren — jede Datumszelle der Anwendung läuft hier durch.
     */
    private static function format(CarbonInterface $dt, string $format): string {
        return strpbrk($format, 'DlMFSaAN\\') === false ? $dt->rawFormat($format) : $dt->translatedFormat($format);
    }

    /** Leistungszeitraum „von – bis“ bzw. nur der Beginn; null ohne Beginn. */
    public static function period(?CarbonInterface $from, ?CarbonInterface $to): ?string {
        if ($from === null) {
            return null;
        }

        return $to === null ? self::fdate($from) : self::fdate($from) . ' – ' . self::fdate($to);
    }

    /**
     * Datum + Uhrzeit in Anzeige-Zeitzone + konfiguriertem Format. Optional
     * mit expliziter Zeitzone (z. B. die des EMPFÄNGERS beim Push-Versand,
     * wo Tz::current() den Auslöser bzw. Queue-Kontext sähe — W4.2).
     */
    public static function fdatetime(CarbonInterface $dt, ?string $tz = null): string {
        return self::format($dt->copy()->setTimezone($tz ?? Tz::current()), Formats::dateTime());
    }

    /** Uhrzeit in Anzeige-Zeitzone + konfiguriertem Format. */
    public static function ftime(CarbonInterface $dt): string {
        return self::format($dt->copy()->setTimezone(Tz::current()), Formats::time());
    }
}
