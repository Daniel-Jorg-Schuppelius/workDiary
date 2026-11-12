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
    // Pflege und Abweichungen (MVP-1611).
    'route' => [
        'edit_title' => 'Route bearbeiten',
        'responsible' => 'Verantwortlich für Abweichungen',
        'responsible_hint' => 'Erhält den offenen Punkt bei Abweichungen. Leer: die Teamleitung der Person, die den Rundgang durchführt.',
        'updated' => 'Route gespeichert.',
        'deactivate_confirm' => 'Route deaktivieren? Ein laufender Rundgang darf zu Ende gehen, neue lassen sich nicht starten.',
        'deactivated' => 'Route deaktiviert — sie lässt sich nicht mehr starten.',
        'activated' => 'Route ist wieder aktiv.',
    ],
    'checkpoint' => [
        'edit_title' => 'Kontrollpunkt bearbeiten',
        'edit_hint' => 'Änderungen gelten für künftige Scans; erfasste Scans behalten ihre Bewertung.',
        'updated' => 'Kontrollpunkt „:label“ gespeichert.',
        'deactivate_confirm' => 'Kontrollpunkt deaktivieren? Er wird ab sofort nicht mehr erwartet und lässt sich nicht mehr scannen; frühere Rundgänge bleiben unverändert.',
        'deactivated_done' => 'Kontrollpunkt „:label“ deaktiviert.',
        'deactivated' => 'Dieser Kontrollpunkt ist deaktiviert.',
        'inactive_badge' => 'deaktiviert',
    ],
    'deviation' => [
        'running_title' => 'Rundgang „:route“ weicht ab',
        'late_line' => '„:label“ außerhalb des Fensters (:delta min)',
        'missed_line' => ':count fällige Kontrollpunkte ohne Scan',
        'overdue_title' => 'Rundgang „:route“ nicht beendet',
        'overdue_line' => 'Soll-Dauer von :minutes Minuten überschritten (Start :start).',
    ],
];
