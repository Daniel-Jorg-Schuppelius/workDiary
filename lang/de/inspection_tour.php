<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_tour.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfertouren (MVP-918).
return [
    'title' => 'Prüfertour',
    'subtitle' => 'Fällige Prüftermine eines Prüfers als Tour planen: Jeder Termin wird ein Auftrag am Gerätestandort, die Reihenfolge optimiert die Tourenplanung.',
    'inspector' => 'Prüfer',
    'due_until' => 'Fällig bis',
    'date' => 'Tourdatum',
    'select' => 'Auswählen',
    'location' => 'Standort',
    'no_coordinates' => 'ohne Koordinaten',
    'no_coordinates_hint' => 'Ohne Koordinaten am Gerät bleibt der Stopp in der Tour, fließt aber nicht in die Routenberechnung ein.',
    'none' => 'Keine offenen Prüftermine dieses Prüfers im Zeitraum.',
    'none_selected' => 'Bitte mindestens einen offenen Prüftermin wählen.',
    'unavailable' => 'Die Tourenplanung ist für Ihre Organisation nicht aktiv; Prüfertouren sind erst mit dem Modul Planung möglich.',
    'plan' => 'Tour planen',
    'planned' => ':count Prüfungen als Tour geplant.',
    'entry_title' => 'Prüfung :profile – :asset',
    'entry_content' => 'Prüftermin aus der Prüfertour, fällig am :due.',
];
