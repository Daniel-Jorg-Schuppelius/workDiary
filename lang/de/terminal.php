<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : terminal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Stempelterminals',
    'intro' => 'Fest montierte RFID-/NFC-Terminals stempeln Mitarbeitende ohne Dienstgerät ein und aus. Die Ereignisse laufen in dieselbe Anwesenheitslogik wie Browser-Stempelungen (Korrekturen, Auswertungen). Gerätetoken und Badge-Kennungen werden nur gehasht gespeichert.',

    'new_heading' => 'Ingest-URL des Terminals',
    'new_hint' => 'Jetzt im Terminal hinterlegen — der Token wird nur dieses eine Mal angezeigt.',

    'terminals_heading' => 'Terminals',
    'no_terminals' => 'Noch kein Terminal registriert.',
    'badges_heading' => 'Badges',
    'no_badges' => 'Noch kein Badge zugeordnet.',

    'field' => [
        'name' => 'Bezeichnung',
        'name_placeholder' => 'z. B. Halle Nord',
        'site' => 'Standort',
        'no_site' => '— ohne Standort —',
    ],

    'badge' => [
        'user' => 'Mitarbeiter',
        'label' => 'Bezeichnung',
        'uid' => 'Badge-Kennung',
        'uid_placeholder' => 'RFID-/NFC-UID',
        'uid_help' => 'Wird nur als Hash gespeichert (keine Klartext-Kennung).',
        'validity' => 'Gültigkeit',
        'valid_from' => 'Gültig ab',
        'valid_until' => 'Gültig bis',
        'outside_validity' => 'außerhalb',
    ],

    'action' => [
        'register' => 'Registrieren',
        'disable' => 'Sperren',
        'assign' => 'Zuordnen',
        'revoke' => 'Sperren',
        'rotate' => 'Token rotieren',
        'rotate_help' => 'Neuen Gerätetoken erzeugen — der alte ist sofort ungültig.',
    ],

    'col' => [
        'status' => 'Status',
        'status_display' => 'Status-Anzeige',
        'last_seen' => 'Zuletzt gesehen',
    ],

    'status_display' => [
        'on' => 'An',
        'off' => 'Aus',
        'help' => 'Zeigt nach dem Stempeln Gleitzeitsaldo/Resturlaub am Gerät (für Umstehende sichtbar) — Standard aus.',
    ],

    'buffer' => [
        'label' => 'Puffer',
        'help' => 'Vom Terminal gemeldete, noch nicht übertragene Offline-Ereignisse.',
    ],

    'status' => [
        'active' => 'Aktiv',
        'inactive' => 'Gesperrt',
        'revoked' => 'Gesperrt',
    ],

    'flash' => [
        'registered' => 'Terminal registriert.',
        'terminal_disabled' => 'Terminal gesperrt.',
        'badge_assigned' => 'Badge zugeordnet.',
        'badge_revoked' => 'Badge gesperrt.',
        'badge_taken' => 'Diese Badge-Kennung ist bereits vergeben.',
        'token_rotated' => 'Gerätetoken rotiert — neue Ingest-URL einmalig sichtbar.',
        'status_enabled' => 'Status-Anzeige aktiviert.',
        'status_disabled' => 'Status-Anzeige deaktiviert.',
    ],
];
