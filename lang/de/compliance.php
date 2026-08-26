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
            'category' => 'Bereich',
            'all_categories' => 'Alle Bereiche',
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
            'frameTime' => 'Rahmenzeit',
            'coreTime' => 'Kernarbeitszeit',
            'entryBreakMissing' => 'Pflichtpause (Projektzeit)',
            'missingCheckout' => 'Geht-Stempelung fehlt',
            'freeDayStamp' => 'Stempelung an freiem Tag',
            'absenceStamp' => 'Stempelung trotz Abwesenheit',
            'attendanceFrameTime' => 'Rahmenzeit (Stempelzeiten)',
            'lateRecording' => 'Verspätete Erfassung (MiLoG)',
            'sixMonthAverage' => '6-Monats-Durchschnitt (§ 3 ArbZG)',
            'nightWork' => 'Nachtarbeit über 8 h (§ 6 ArbZG)',
            'substituteRestDay' => 'Ersatzruhetag fehlt (§ 11 ArbZG)',
            'freeSundays' => 'Freie Sonntage unterschritten (§ 11 ArbZG)',
            // Feature 144 (MVP-719): Lenk-/Ruhezeiten (VO (EG) 561/2006 / FPersV).
            'dailyDriving' => 'Tageslenkzeit (Art. 6 VO 561/2006)',
            'weeklyDriving' => 'Wochenlenkzeit (Art. 6 VO 561/2006)',
            'fortnightDriving' => 'Doppelwochen-Lenkzeit (Art. 6 VO 561/2006)',
            'drivingBreakMissing' => 'Fahrtunterbrechung fehlt (Art. 7 VO 561/2006)',
            'dailyRest' => 'Tägliche Ruhezeit (Art. 8 VO 561/2006)',
            'weeklyRest' => 'Wöchentliche Ruhezeit (Art. 8 VO 561/2006)',
        ],
        'unit' => [
            'days' => '{1} :count Tag|[2,*] :count Tage',
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
    'history' => [
        'title' => 'Compliance-Verstöße',
        'nav' => 'Verstoß-Historie',
        'subtitle' => 'Persistierte ArbZG-Verstöße mit Bearbeitungsstand und Quittierung.',
        'to_report' => 'Einzelreport',
        'to_dashboard' => 'Dashboard',
        'filter' => [
            'status' => 'Status',
            'all' => 'Alle Status',
            'category' => 'Kategorie',
        ],
        'col' => [
            'employee' => 'Mitarbeiter',
            'status' => 'Status',
        ],
        'empty' => 'Keine persistierten Verstöße.',
        'note_placeholder' => 'Begründung (Pflicht bei „akzeptiert")',
        'btn' => [
            'acknowledge' => 'Quittieren',
            'accept' => 'Akzeptieren',
            'correction' => 'Korrekturantrag',
        ],
        'category' => [
            'arbzg' => 'ArbZG',
            'plausibility' => 'Ungeklärte Fälle',
            'drivingTime' => 'Lenkzeiten',
        ],
        'acknowledged' => 'Verstoß aktualisiert.',
        'error' => [
            'invalid_status' => 'Ungültiger Zielstatus.',
            'not_acknowledgeable' => 'Dieser Verstoß kann nicht mehr quittiert werden.',
            'note_required' => 'Für „akzeptiert" ist eine Begründung erforderlich.',
        ],
    ],
    'milog' => [
        'button' => 'MiLoG-Nachweis (Zoll)',
        'csv' => [
            'employee' => 'Mitarbeiter',
            'personnel_number' => 'Personalnummer',
            'date' => 'Datum',
            'start' => 'Beginn',
            'end' => 'Ende',
            'breaks' => 'Pausen (Min.)',
            'duration' => 'Dauer',
        ],
    ],
    'driving' => [
        'button' => 'Lenkzeit-Nachweis',
        'title' => 'Lenk- und Ruhezeiten-Nachweis',
        'thresholds_note' => 'Lenk-/Ruhezeiten (VO (EG) 561/2006 / FPersV): max. 9 h Lenkzeit/Tag (2×/Woche 10 h) · 56 h/Woche · 90 h/Doppelwoche · 45 min Fahrtunterbrechung nach 4,5 h (teilbar 15 + 30) · Ruhezeit 11 h/Tag (max. 3×/Woche 9 h) · 45 h/Woche (24 h mit Ausgleich).',
        'disclaimer' => 'Datenbasis sind die erfassten Fahrten (Fahrtenbuch) mit markierten Fahrzeugen; Tachograph-/DTCO-Daten werden nicht gelesen. Keine Rechtsberatung.',
        'csv' => [
            'driver' => 'Fahrer',
            'personnel_number' => 'Personalnummer',
            'date' => 'Datum',
            'vehicles' => 'Fahrzeuge',
            'start' => 'Erste Abfahrt',
            'end' => 'Letzte Ankunft',
            'driving' => 'Lenkzeit',
            'longest_stint' => 'Längste Lenkphase ohne Unterbrechung',
            'breaks' => 'Unterbrechungen (Min.)',
            'rest_before' => 'Ruhezeit davor',
            'findings' => 'Befunde',
        ],
        'badge' => [
            'label' => 'Lenkzeit',
            'remaining' => ':remaining frei',
            'until_break' => 'Pause in :until',
            'break_due' => 'Pause fällig',
            'exhausted' => 'Tageslenkzeit erschöpft',
            'title' => 'Rest-Tageslenkzeit :daily (Limit :limit) · nächste Fahrtunterbrechung in :until · Wochenrest :weekly · Doppelwoche :fortnight',
        ],
    ],
];
