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
    'kiosk' => [
        'pin_toggle' => 'Ausweis vergessen? Mit PIN stempeln',
        'pin_submit' => 'Stempeln',
        'heading' => 'Kiosk-Adresse',
        'hint' => 'Im Browser eines Tablets öffnen — das Tablet wird zum Stempelterminal. Enthält dasselbe Gerätetoken; nur einmal sichtbar.',
        'title' => 'Stempelterminal',
        'intro' => 'Ausweis an den Leser halten.',
        'mode' => 'Buchungsart',
        'mode_work' => 'Kommen / Gehen',
        'mode_break' => 'Pause',
        'badge_label' => 'Ausweis',
        'nfc_start' => 'NFC dieses Geräts nutzen',
        'nfc_active' => 'NFC liest',
        'flex_balance' => 'Gleitzeit:',
        'status' => [
            'invalid_pin' => 'Personalnummer oder PIN ungültig',
            'clocked_in' => 'Kommen gebucht',
            'clocked_out' => 'Gehen gebucht',
            'break_started' => 'Pause begonnen',
            'break_ended' => 'Pause beendet',
            'noop' => 'Keine offene Anwesenheit',
            'skipped' => 'Bereits gebucht',
            'unknown_badge' => 'Ausweis unbekannt',
            'rejected' => 'Buchung abgelehnt',
            'invalid_token' => 'Terminal gesperrt',
            'unavailable' => 'Stempeln gerade nicht möglich',
            'network' => 'Keine Verbindung — bitte erneut versuchen',
            'nfc_unavailable' => 'NFC ist auf diesem Gerät nicht verfügbar',
            'error' => 'Fehler bei der Buchung',
        ],
    ],
    'checkpoint' => [
        'heading' => 'Check-in-Punkte (QR/NFC)',
        'intro' => 'Ein Code am Standort oder Fahrzeug: Mitarbeitende scannen ihn mit dem eigenen Gerät und stempeln angemeldet. Dieselbe Adresse lässt sich auf einen NFC-Aufkleber schreiben.',
        'empty' => 'Noch keine Check-in-Punkte.',
        'action' => [
            'create' => 'Check-in-Punkt anlegen',
            'qr' => 'QR-Code drucken',
            'enable' => 'Freigeben',
        ],
        'field' => [
            'kind' => 'Art',
            'location' => 'Standort / Fahrzeug',
            'vehicle' => 'Fahrzeug',
            'radius' => 'Umkreis (m)',
            'location_check' => 'Ortsprüfung (optional)',
            'latitude' => 'Breitengrad',
            'longitude' => 'Längengrad',
        ],
        'help' => [
            'site' => 'Nur bei der Art „Standort“.',
            'vehicle' => 'Pflicht bei der Art „Fahrzeug“.',
            'location_check' => 'Ein Code lässt sich abfotografieren. Mit Umkreis muss das Gerät beim Stempeln in der Nähe sein; ohne eigene Koordinaten gilt die des Standorts. Die Position wird nicht gespeichert.',
        ],
        'error' => [
            'radius_without_center' => 'Für den Umkreis fehlt ein Ort: Koordinaten eintragen oder einen Standort mit Geokoordinaten wählen.',
            'vehicle' => 'Das Fahrzeug wurde nicht gefunden.',
        ],
        'flash' => [
            'created' => 'Check-in-Punkt angelegt.',
            'enabled' => 'Check-in-Punkt freigegeben.',
            'disabled' => 'Check-in-Punkt gesperrt.',
        ],
        'qr' => [
            'alt' => 'QR-Code für den Check-in „:name“',
            'hint' => 'Mit dem Handy scannen, anmelden und Kommen oder Gehen bestätigen.',
            'nfc_hint' => 'Für einen NFC-Aufkleber diese Adresse mit einer NFC-App als Web-Adresse (URL) auf den Aufkleber schreiben.',
        ],
    ],
    'pin' => [
        'heading' => 'Terminal-PINs',
        'intro' => 'Ausweis vergessen? Mit Personalnummer und PIN lässt sich am Terminal und im Kiosk trotzdem stempeln. Gespeichert wird nur ein Hash; nach 5 Fehlversuchen ist die PIN 15 Minuten gesperrt.',
        'empty' => 'Noch keine PINs vergeben.',
        'action' => [
            'set' => 'PIN setzen',
            'unlock' => 'Entsperren',
            'remove' => 'Entfernen',
        ],
        'field' => [
            'pin' => 'PIN',
            'pin_confirmation' => 'PIN wiederholen',
            'personnel_number' => 'Personalnummer',
        ],
        'help' => [
            'dialog' => '4 bis 8 Ziffern. Die Person erfährt die PIN von Ihnen — sie ist danach nicht mehr einsehbar.',
            'personnel_number' => 'Nur Personen mit Personalnummer — sie ist der zweite Teil der Anmeldung am Terminal.',
        ],
        'status' => [
            'locked_until' => 'gesperrt bis :time',
        ],
        'confirm' => [
            'remove' => 'PIN wirklich entfernen? Die Person kann danach nur noch mit Ausweis stempeln.',
        ],
        'error' => [
            'format' => 'Die PIN muss aus :min bis :max Ziffern bestehen.',
            'personnel_number' => 'Die Person hat keine Personalnummer — ohne sie ist die PIN am Terminal nicht nutzbar.',
        ],
        'flash' => [
            'set' => 'PIN gesetzt.',
            'unlocked' => 'PIN entsperrt.',
            'removed' => 'PIN entfernt.',
        ],
    ],
];
