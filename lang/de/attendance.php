<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : attendance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Zwischen-Status (MVP-532): Homeoffice/Dienstgang.
    'intermediate' => [
        'homeoffice' => 'Homeoffice',
        'errand' => 'Dienstgang',
        'start_homeoffice' => 'Homeoffice beginnen',
        'end_homeoffice' => 'Homeoffice beenden',
        'start_errand' => 'Dienstgang beginnen',
        'end_errand' => 'Dienstgang beenden',
    ],
    'status' => [
        'open' => 'Offen',
        'closed' => 'Abgeschlossen',
        'auto_closed' => 'Auto-abgeschlossen',
        'adjusted' => 'Angepasst',
        'cancelled' => 'Storniert',
    ],
    'source' => [
        'clock' => 'Stempelung',
        'manual' => 'Manuell',
        'import' => 'Import',
        'auto_close' => 'Auto-Abschluss',
        'terminal' => 'Terminal',
        'phone' => 'Telefon',
        'learning' => 'Lernzeit',
    ],
    'correction' => [
        'action' => [
            'create' => 'Anlegen',
            'update' => 'Ändern',
            'delete' => 'Löschen',
        ],
    ],
    'error' => [
        'target_day_locked' => 'Der Zieltag ist abgeschlossen oder der Monat freigegeben — bitte eine Zeitkorrektur beantragen.',
        'duration_too_long' => 'Eine Stempelung darf nicht länger als :hours Stunden dauern.',
    ],
];
