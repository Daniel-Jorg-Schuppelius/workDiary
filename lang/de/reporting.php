<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reporting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'target' => [
        'nav' => 'Zielwerte',
        'title' => 'Zielwerte & Benchmarks',
        'subtitle' => 'Soll-Werte je Kennzahl hinterlegen – die Reports zeigen Soll/Ist und die Abweichung.',
        'create' => 'Zielwert anlegen',
        'edit' => 'Zielwert bearbeiten',
        'empty' => 'Noch keine Zielwerte hinterlegt.',
        'metric_label' => 'Kennzahl',
        'scope_label' => 'Bezug',
        'scope_ref' => 'Bezugsobjekt',
        'scope_ref_hint' => 'Nur bei Kunde/Projekt/Mitarbeitendem auswählen.',
        'value_label' => 'Zielwert',
        'period_label' => 'Bezugszeitraum',
        'valid_from' => 'Gültig ab',
        'valid_until' => 'Gültig bis',
        'note_label' => 'Notiz',
        'created' => 'Zielwert wurde angelegt.',
        'updated' => 'Zielwert wurde aktualisiert.',
        'deleted' => 'Zielwert wurde gelöscht.',
        'delete_confirm' => 'Zielwert wirklich löschen?',
        'none' => '–',
        'soll' => 'Soll',
        'ist' => 'Ist',
        'deviation' => 'Abweichung',
        'met' => 'erreicht',
        'missed' => 'verfehlt',
        'no_target' => 'Kein Zielwert',
        'metric' => [
            'contributionMargin' => 'Deckungsbeitrags-Marge (%)',
            'billableRate' => 'Abrechenbare Quote (%)',
            'reworkShare' => 'Nacharbeitsanteil (%)',
            'slaComplianceRate' => 'SLA-Einhaltungsquote (%)',
            'utilization' => 'Auslastung (%)',
        ],
        'scope' => [
            'org' => 'Organisation (global)',
            'customer' => 'Kunde',
            'project' => 'Projekt',
            'user' => 'Mitarbeitende(r)',
        ],
        'period' => [
            'month' => 'Monat',
            'quarter' => 'Quartal',
            'year' => 'Jahr',
        ],
    ],

    'cohort' => [
        'nav' => 'Kohortenvergleich',
        'title' => 'Kohortenvergleich (vor/nach Fortbildung)',
        'subtitle' => 'Vergleicht eine Kennzahl je Mitarbeitendem im Zeitraum vor und nach dem Erwerb einer Fortbildung.',
        'qualification' => 'Fortbildung / Qualifikation',
        'metric' => [
            'billableRate' => 'Abrechenbare Quote (%)',
            'reworkShare' => 'Nacharbeitsanteil (%)',
        ],
        'metric_label' => 'Kennzahl',
        'window' => 'Vergleichsfenster (Tage)',
        'choose' => 'Bitte eine Fortbildung wählen.',
        'member' => 'Mitarbeitende(r)',
        'acquired_on' => 'Erworben am',
        'before' => 'Vorher',
        'after' => 'Nachher',
        'delta' => 'Δ',
        'improved' => 'Verbessert',
        'no_date' => 'kein Erwerbsdatum',
        'no_date_hint' => 'Ohne hinterlegtes Erwerbsdatum (Qualifikation „gültig ab") kann kein Vor/Nach-Schnitt gebildet werden.',
        'no_data_window' => 'Nicht genügend Zeitbuchungen in einem der Fenster.',
        'aggregate' => 'Kohorte gesamt (Mittel)',
        'members_with_date' => 'mit Erwerbsdatum',
        'members_without_date' => 'ohne Erwerbsdatum',
        'improved_count' => 'verbessert',
        'data_note' => 'Datenquelle Erwerbsdatum: „gültig ab" der Qualifikationszuordnung. Die Kennzahlen werden aus denselben Zeitbuchungsfeldern (abrechenbar/nicht abrechenbar) wie die Wirtschaftlichkeitssicht gebildet.',
    ],
];
