<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Tz.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use App\Models\{Organization, User};
use Carbon\{CarbonImmutable, CarbonInterface};
use DateTimeZone;
use Illuminate\Support\Facades\Auth;

/**
 * Zentrale Auflösung und Anwendung der "Anzeige-Zeitzone".
 *
 * Grundsatz: In der Datenbank wird durchgängig UTC gespeichert
 * (config('app.timezone') === 'UTC'). Die Umrechnung in die für den Nutzer
 * sichtbare Zeitzone passiert ausschließlich an den Rändern – bei der Anzeige,
 * bei Tagesgrenzen ("heute", Wochenstart) und beim Parsen manueller Eingaben.
 *
 * Auflösungsreihenfolge der aktiven Zeitzone:
 *   1. Persönliche Zeitzone des angemeldeten Benutzers (users.timezone)
 *   2. Zeitzone der aktiven Organisation (currentOrganization->timezone)
 *   3. config('app.display_timezone')
 *   4. 'Europe/Berlin'
 */
final class Tz {
    public const FALLBACK = 'Europe/Berlin';

    /**
     * Liefert die aktuell gültige Anzeige-Zeitzone als IANA-Bezeichner.
     */
    public static function current(): string {
        // 1) User-Override
        $user = Auth::user();
        if ($user instanceof User) {
            $userTz = $user->timezone;
            if ($userTz !== null && self::isValid($userTz)) {
                return $userTz;
            }
        }

        // 2) Aktive Organisation
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization && is_string($org->timezone) && self::isValid($org->timezone)) {
                return $org->timezone;
            }
        }

        // 3)/4) Konfig-Fallback
        $configured = (string) config('app.display_timezone', self::FALLBACK);

        return self::isValid($configured) ? $configured : self::FALLBACK;
    }

    /**
     * Wandelt einen Zeitpunkt in die aktive Anzeige-Zeitzone um.
     * Gibt null durch, damit Aufrufer mit optionalen Werten arbeiten können.
     */
    public static function toLocal(?CarbonInterface $dt): ?CarbonInterface {
        return $dt?->copy()->setTimezone(self::current());
    }

    /**
     * "Jetzt" in der aktiven Anzeige-Zeitzone.
     */
    public static function now(): CarbonImmutable {
        return CarbonImmutable::now(self::current());
    }

    /**
     * Tagesbeginn von $dt (oder jetzt) in der aktiven Anzeige-Zeitzone.
     */
    public static function startOfDay(?CarbonInterface $dt = null): CarbonImmutable {
        $base = $dt ? CarbonImmutable::instance($dt->toDateTimeImmutable()) : CarbonImmutable::now();

        return $base->setTimezone(self::current())->startOfDay();
    }

    /**
     * Interpretiert eine Nutzereingabe als Wert in der aktiven Anzeige-Zeitzone
     * und gibt ihn zur Speicherung in UTC zurück.
     */
    public static function parse(string $value, ?string $format = null): CarbonImmutable {
        $tz = self::current();
        $parsed = $format !== null
            ? CarbonImmutable::createFromFormat($format, $value, $tz)
            : CarbonImmutable::parse($value, $tz);
        if (! $parsed instanceof CarbonImmutable) {
            // createFromFormat kann bei unpassendem Format false liefern.
            $parsed = CarbonImmutable::parse($value, $tz);
        }

        return $parsed->setTimezone('UTC');
    }

    /**
     * Bequemer Wrapper für Controller/Requests: interpretiert eine
     * datetime-local-Eingabe als Org-lokal und liefert einen UTC-DB-String
     * ('Y-m-d H:i:s'). Leere Eingaben → null; ungültige bleiben unverändert,
     * damit die 'date'-Validierungsregel sie meldet.
     */
    public static function toUtcString(?string $value): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return self::parse(trim($value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * Prüft, ob $tz ein gültiger IANA-Zeitzonen-Bezeichner ist.
     */
    public static function isValid(?string $tz): bool {
        if ($tz === null || $tz === '') {
            return false;
        }

        return in_array($tz, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Liste aller Zeitzonen-Bezeichner, gruppiert nach Region – für Dropdowns.
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array {
        $groups = [];
        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $region = str_contains($identifier, '/')
                ? (string) strstr($identifier, '/', true)
                : (string) __('Sonstige');
            $groups[$region][] = $identifier;
        }
        ksort($groups);

        return $groups;
    }
}
