<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : project.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Standardprojekt pro Kunde
    |--------------------------------------------------------------------------
    |
    | Wird automatisch beim Anlegen eines Kunden erzeugt und dient als
    | Default-Bucket für ad-hoc-/Notfallaufträge ohne explizit gewähltes
    | Projekt. Pro Kunde existiert genau ein Standardprojekt.
    */
    'default_project' => [
        'name' => env('PROJECT_DEFAULT_NAME', 'Wartung'),
        'color' => env('PROJECT_DEFAULT_COLOR', '#64748b'),
        // null (Default) = Abrechenbarkeit vom Kunden erben; explizit true/false
        // setzt ein Override am Standardprojekt, das das Kunden-Flag übersteuert.
        'billable' => env('PROJECT_DEFAULT_BILLABLE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schlüsselwort-Zuordnung importierter Zeiten (MVP-483)
    |--------------------------------------------------------------------------
    |
    | Enthält der Text einer importierten Zeit (Fernwartungs-Notiz, Toggl-
    | Beschreibung, …) den Namen oder ein Synonym eines Projekts DESSELBEN
    | Kunden, wird sie diesem Projekt zugeordnet statt im Standardprojekt bzw.
    | in der Zuordnungs-Inbox zu landen. Nur eindeutige Treffer buchen.
    */
    'keyword_matching' => [
        'enabled' => (bool) env('PROJECT_KEYWORD_MATCHING', true),

        /* Mindestlänge aus dem Projektnamen abgeleiteter Begriffe. */
        'min_token_length' => (int) env('PROJECT_KEYWORD_MIN_LENGTH', 4),

        /*
         * Namensbestandteile, die nie allein zuordnen — sie kommen in beinahe
         * jedem Support-Text vor und träfen sonst wahllos.
         */
        'stopwords' => [
            'wartung', 'support', 'service', 'projekt', 'allgemein', 'sonstiges',
            'sonstige', 'intern', 'extern', 'arbeiten', 'beratung', 'betreuung',
            'diverse', 'diverses', 'kunde', 'kunden', 'aufgaben', 'aufgabe',
            'standard', 'pauschale', 'stunden', 'zeiten', 'edv', 'it',
        ],
    ],
];
