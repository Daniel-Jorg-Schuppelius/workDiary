<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : patrol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Wachrundgänge (Phase 156).
return [
    'report' => [
        'title' => 'Rundgangsbericht',
        'subtitle' => 'Rundgänge je Route und Objekt im Zeitraum — Soll, pünktlich, verspätet und verpasst.',
        'expected' => 'Soll-Punkte',
        'onTime' => 'pünktlich',
        'late' => 'verspätet',
        'missed' => 'verpasst',
        'note' => 'Begründung',
        'runs' => 'Rundgänge',
        'punctuality' => 'Pünktlichkeit',
        'aborted' => 'abgebrochen',
        'allRoutes' => 'Alle Routen',
        'allSites' => 'Alle Objekte',
        'empty' => 'Keine Rundgänge im Zeitraum.',
        'summary' => ':runs Rundgänge, davon :completed abgeschlossen und :aborted abgebrochen',
    ],
    'scan' => [
        'implausibleTime' => 'Die Scanzeit des Geräts liegt außerhalb des Rundgangs — Uhrzeit des Geräts prüfen.',
    ],
];
