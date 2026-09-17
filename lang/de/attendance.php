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
        'checkin' => 'Check-in (QR/NFC)',
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
    'checkpoint_kind' => [
        'site' => 'Standort',
        'vehicle' => 'Fahrzeug',
    ],
    'checkin' => [
        'title' => 'Check-in',
        'subtitle' => 'Kommen und Gehen über den Code am Standort oder Fahrzeug.',
        'state' => [
            'in' => 'Du bist seit :time Uhr eingestempelt.',
            'out' => 'Du bist gerade nicht eingestempelt.',
        ],
        'action' => [
            'in' => 'Kommen',
            'out' => 'Gehen',
        ],
        'location_hint' => 'Beim Stempeln wird einmal die Position geprüft (Umkreis :radius m). Gespeichert wird sie nicht.',
        'flash' => [
            'in' => 'Kommen an „:name“ gebucht.',
            'out' => 'Gehen an „:name“ gebucht.',
        ],
        'error' => [
            'already_in' => 'Du bist bereits eingestempelt.',
            'not_in' => 'Du bist nicht eingestempelt.',
            'no_center' => 'Für diesen Punkt ist ein Umkreis, aber kein Ort hinterlegt. Bitte an die Verwaltung wenden.',
            'location_required' => 'Für diesen Punkt wird die Position benötigt.',
            'too_far' => 'Du bist :distance m entfernt, erlaubt sind :radius m.',
            'location_denied' => 'Die Position konnte nicht ermittelt werden. Bitte Ortungsfreigabe erlauben.',
        ],
    ],
];
