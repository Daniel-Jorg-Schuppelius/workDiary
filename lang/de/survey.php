<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : survey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Umfragen (Feature 090, Phase 156).
return [
    'trend' => [
        'title' => 'NPS je Monat',
        'unit' => 'NPS',
    ],
    'triggers' => [
        'title' => 'Auslöser',
        'order' => 'Nach Auftragsabschluss automatisch einladen',
        'course' => 'Nach Kursabschluss automatisch einladen',
        'schedule' => 'Im festen Abstand einladen (Tage)',
        'scheduleHint' => 'Kunden mit abgeschlossenem Auftrag oder gelöstem Ticket im Abstand, höchstens einmal je Abstand. Leer = aus.',
        'saved' => 'Auslöser gespeichert.',
    ],
];
