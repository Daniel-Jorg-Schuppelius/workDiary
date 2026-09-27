<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : rental.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'terms' => [
        'title' => 'Mietbedingungen',
        'signed' => 'Vertrag :contract, Fassung :revision, unterschrieben am :date',
        'missing' => 'Für diesen Kunden liegen keine unterschriebenen Mietbedingungen vor.',
        'missing_required' => 'Keine unterschriebenen Mietbedingungen — die Übergabe ist erst danach möglich.',
        'create_agreement' => 'Mietbedingungen anlegen',
        'required' => 'Übergabe erst mit unterschriebenen Mietbedingungen des Kunden (Einstellung der Organisation).',
    ],
    // Direktbuchung und Preisangabe im Portal (MVP-916).
    'portal' => [
        'price' => 'Preis (netto)',
        'price_estimate' => 'ca. :amount',
        'direct_intro' => 'Freie Geräte buchen Sie direkt; alternativ fragen Sie Gerät oder Gerätegruppe an, und wir bestätigen verbindlich. Die Preisangabe richtet sich nach der Preisliste und ist netto.',
        'direct_book' => 'Direkt buchen',
        'direct_hint' => 'Reserviert das gewählte Gerät sofort verbindlich, sofern es im Zeitraum frei ist.',
        'direct_booked' => 'Gerät verbindlich reserviert — Sie erhalten die Unterlagen zur Übergabe von uns.',
        'direct_status' => 'Direkt gebucht',
        'direct_disabled' => 'Die Direktbuchung ist nicht freigeschaltet.',
        'direct_case_note' => 'Direktbuchung aus dem Kundenportal.',
        'direct_notification' => 'Direktbuchung von :customer',
    ],
    // Mietpreisregeln (MVP-950).
    'rule' => [
        'title' => 'Mietpreisregeln',
        'empty' => 'Keine Regeln: Es gilt der Tagessatz.',
        'add' => 'Regel ergänzen',
        'line' => ':label (:percent %)',
        'from_utilization' => 'ab :percent % Auslastung',
        'kind' => [
            'season' => 'Saison',
            'weekday' => 'Wochentage',
            'utilization' => 'Auslastung',
        ],
        'field' => [
            'kind' => 'Art',
            'label' => 'Bezeichnung',
            'valid_from' => 'Gültig ab',
            'valid_until' => 'Gültig bis',
            'weekdays' => 'Wochentage',
            'utilization_min_percent' => 'Ab Auslastung (%)',
            'adjust_percent' => 'Auf-/Abschlag (%)',
        ],
        'weekday' => [
            '1' => 'Mo',
            '2' => 'Di',
            '3' => 'Mi',
            '4' => 'Do',
            '5' => 'Fr',
            '6' => 'Sa',
            '7' => 'So',
        ],
        'flash' => [
            'saved' => 'Mietpreisregel gespeichert.',
            'deleted' => 'Mietpreisregel entfernt.',
        ],
    ],
];
