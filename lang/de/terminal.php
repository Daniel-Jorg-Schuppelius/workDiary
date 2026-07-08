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
    ],

    'action' => [
        'register' => 'Registrieren',
        'disable' => 'Sperren',
        'assign' => 'Zuordnen',
        'revoke' => 'Sperren',
    ],

    'col' => [
        'status' => 'Status',
        'last_seen' => 'Zuletzt gesehen',
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
    ],
];
