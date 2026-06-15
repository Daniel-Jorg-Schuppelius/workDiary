<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : holidays.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Feiertags-Rechtsraum (Feature 034)
    |--------------------------------------------------------------------------
    |
    | Provider ist ein Yasumi-Provider-Pfad und bestimmt, welche Feiertage
    | (insb. regionale wie Fronleichnam, Reformationstag) gelten:
    |
    |   "Germany"                       — bundesweite Feiertage (ohne regionale)
    |   "Germany\\Bavaria"              — Bayern (inkl. Fronleichnam, Mariä Himmelfahrt)
    |   "Germany\\Berlin"               — Berlin
    |   "Germany\\NorthRhineWestphalia" — NRW (inkl. Fronleichnam, Allerheiligen)
    |   "Austria", "Switzerland\\...", … — andere Rechtsräume
    |
    | Diese Werte sind der systemweite Default. Eine Organisation kann den
    | Rechtsraum über settings['holidays']['provider'] (+ optional 'locale')
    | übersteuern; der mandantenbewusste Zugriff erfolgt über
    | \App\Support\Setting::get('holidays.provider'). Sowohl der
    | SurchargeCalculator als auch die Compliance-Regeln lesen ausschließlich
    | über den HolidayService und nutzen damit automatisch dieselbe Quelle.
    |
    */

    'provider' => env('HOLIDAYS_PROVIDER', 'Germany\\Berlin'),
    'locale' => env('HOLIDAYS_LOCALE', 'de_DE'),
];
