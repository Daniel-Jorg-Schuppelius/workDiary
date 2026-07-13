<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationText.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Benachrichtigungs-/Aufgabentexte in der Sprache des BETRACHTERS statt des
 * Erzeugers: Scheduler/Queue laufen in der App-Default-Locale — ein dort mit
 * __() fertig gerenderter Titel bliebe für alle Empfänger deutsch. Deshalb
 * werden Lang-Key + Parameter gespeichert und erst bei der Anzeige (bzw. beim
 * Versand je Empfänger) aufgelöst.
 *
 * Parameter-Formen:
 *   - Skalar                              → unverändert
 *   - ISO-Datum '2026-07-13'              → Anzeige-Format (Formats::date)
 *   - ISO-Zeitpunkt '…T06:00:00+02:00'    → Anzeige-TZ + Formats::dateTime
 *   - ['key' => …, 'fallback' => …]       → Trans::or (z. B. Scheduler-Job-Label)
 */
final class NotificationText {
    /**
     * Titel einer gespeicherten database-Notification. Neue Einträge tragen
     * title_key/title_params; Altbestand und Aufrufer ohne Key fallen auf den
     * beim Erzeugen gerenderten title zurück.
     *
     * @param  array<string, mixed>  $data
     */
    public static function title(array $data): string {
        $key = $data['title_key'] ?? null;
        if (! is_string($key) || $key === '') {
            return (string) ($data['title'] ?? '');
        }

        return self::render($key, (array) ($data['title_params'] ?? []));
    }

    /** @param  array<string, mixed>  $params */
    public static function render(string $key, array $params): string {
        return (string) __($key, array_map(self::param(...), $params));
    }

    private static function param(mixed $value): string {
        if (is_array($value) && isset($value['key'])) {
            return Trans::or((string) $value['key'], isset($value['fallback']) ? (string) $value['fallback'] : null);
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2})?/', $value, $m) === 1) {
            try {
                $dt = CarbonImmutable::parse($value);
                if (isset($m[1])) {
                    $dt = $dt->setTimezone(Tz::current());
                }
                // locale() ist laut Signatur auch Getter (string|static) —
                // instanceof hält die Kette typsicher.
                $localized = $dt->locale(Locales::carbon(app()->getLocale()));
                if ($localized instanceof CarbonImmutable) {
                    $dt = $localized;
                }

                return $dt->translatedFormat(isset($m[1]) ? Formats::dateTime() : Formats::date());
            } catch (Throwable) {
                return $value; // sah nur aus wie ein Datum → roh anzeigen
            }
        }

        return (string) $value;
    }
}
