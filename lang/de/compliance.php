<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : compliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'report' => [
        'title' => 'ArbZG-Compliance',
        'nav' => 'ArbZG-Compliance',
        'subtitle' => 'Verstöße gegen das Arbeitszeitgesetz auf Basis der tatsächlich erfassten Arbeitszeit.',
        'empty' => 'Keine Verstöße im Zeitraum.',
        'thresholds_note' => 'Schwellen (ArbZG): max. :daily Netto/Tag · mind. :rest Ruhezeit · max. Ø :weekly/Woche · Pflichtpausen 30 min ab 6 h, 45 min ab 9 h.',
        'corrected' => 'korrigiert',
        'corrected_hint' => 'Für diesen Tag liegt eine genehmigte Zeitkorrektur vor.',
        'drilldown' => 'Zum Tagesabschluss',
        'filter' => [
            'kind' => 'Verstoßart',
            'all' => 'Alle Arten',
        ],
        'kpi' => [
            'total' => 'Verstöße gesamt',
            'employees' => 'Betroffene Mitarbeiter',
        ],
        'kind' => [
            'maxDailyHours' => 'Tageshöchstarbeitszeit',
            'restPeriod' => 'Ruhezeit',
            'breakMissing' => 'Pflichtpause',
            'maxWeeklyHours' => 'Wochenhöchstarbeitszeit',
        ],
        'severity' => [
            'error' => 'Verstoß',
            'warning' => 'Hinweis',
        ],
        'col' => [
            'date' => 'Datum',
            'kind' => 'Art',
            'value' => 'Wert',
            'threshold' => 'Schwelle',
            'severity' => 'Schweregrad',
        ],
        'csv' => [
            'employee' => 'Mitarbeiter',
            'date' => 'Datum',
            'kind' => 'Art',
            'severity' => 'Schweregrad',
            'value' => 'Wert',
            'threshold' => 'Schwelle',
            'corrected' => 'Korrigiert',
            'yes' => 'ja',
        ],
    ],
];
