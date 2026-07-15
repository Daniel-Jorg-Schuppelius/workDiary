<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Offline-Sync (Feature 035): Offline-Änderungs-Seite + Statusanzeige.
return [
    'title' => 'Offline-Änderungen',
    'subtitle' => 'Auf diesem Gerät offline erfasste Aktionen — ausstehend oder abgelehnt.',
    'notice' => 'Diese Liste liegt nur auf diesem Gerät. Ausstehende Einträge werden automatisch übertragen, sobald eine Verbindung besteht; abgelehnte Einträge kannst du erneut anwenden oder verwerfen.',
    'empty' => 'Keine offline erfassten Änderungen vorhanden.',
    'section' => [
        'pending' => 'Ausstehend',
        'rejected' => 'Abgelehnt',
    ],
    'type' => [
        'clock_in' => 'Einstempeln',
        'clock_out' => 'Ausstempeln',
        'comment' => 'Auftrags-Kommentar',
        'form' => 'Formular',
    ],
    'action' => [
        'retry' => 'Erneut anwenden',
        'discard' => 'Verwerfen',
    ],
];
