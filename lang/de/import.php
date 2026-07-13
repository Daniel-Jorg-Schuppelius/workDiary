<?php

declare(strict_types=1);

return [
    'entity' => [
        'customers' => 'Kunden',
        'suppliers' => 'Lieferanten',
        'articles' => 'Artikel',
        'projects' => 'Projekte',
        'users' => 'Benutzer',
        'materials' => 'Material',
        'vehicles' => 'Fahrzeuge',
        'scheduled_shifts' => 'Schichtpläne',
        'tours' => 'Touren',
        'remote_sessions' => 'Fernwartungs-Sitzungen',
    ],

    'state' => [
        'preflight' => 'Vorprüfung',
        'awaitingApproval' => 'Wartet auf Bestätigung',
        'running' => 'Läuft',
        'succeeded' => 'Erfolgreich',
        'partial' => 'Teilweise erfolgreich',
        'failed' => 'Fehlgeschlagen',
    ],

    'errorCode' => [
        'required' => 'Pflichtfeld fehlt',
        'format' => 'Formatfehler',
        'unique' => 'Wert nicht eindeutig',
        'fkMissing' => 'Verweis nicht gefunden',
        'tooLong' => 'Wert zu lang',
        'outOfRange' => 'Wert außerhalb des Bereichs',
        'persist' => 'Speicherfehler',
        'headerMissing' => 'Spalte fehlt',
        'headerUnknown' => 'Spalte unbekannt',
    ],

    'error' => [
        'required' => 'Pflichtfeld :field fehlt.',
        'tooLong' => 'Feld :field überschreitet die maximale Länge von :max Zeichen.',
        'header' => [
            'missing' => 'Pflichtspalte :column fehlt in der CSV-Kopfzeile.',
            'duplicate' => 'Spalte :column kommt mehrfach vor.',
        ],
        'format' => [
            'default' => 'Feld :field hat ein ungültiges Format (:reason).',
            'email' => 'Keine gültige E-Mail-Adresse.',
            'country' => 'Ländercode muss aus 2-3 Großbuchstaben bestehen (ISO 3166-1).',
            'currency' => 'Währungscode muss aus 3 Großbuchstaben bestehen (ISO 4217).',
            'enum' => 'Wert ist kein gültiger Status.',
            'parse' => 'Datei konnte nicht gelesen werden: :reason',
            'xlsxUnreadable' => 'Die Excel-Datei ist beschädigt oder kein gültiges XLSX-Format.',
            'xlsxEmpty' => 'Das erste Tabellenblatt der Excel-Datei enthält keine Zeilen.',
            'date' => 'Kein gültiges Datum (erwartet z. B. „28.05.2026, 09:42:09").',
            'time' => 'Keine gültige Uhrzeit (erwartet HH:MM).',
            'status' => 'Wert ist kein gültiger Status.',
        ],
        'outOfRange' => [
            'rowLimit' => 'Maximale Zeilenanzahl (:max) überschritten — Rest wurde ignoriert.',
        ],
        'fkMissing' => [
            'customer' => 'Kein Kunde mit Nummer :number gefunden.',
            'user' => 'Kein Benutzer mit E-Mail :value gefunden.',
        ],
        'persist' => [
            'noBookingUser' => 'Kein buchbarer Benutzer in der Organisation gefunden.',
        ],
    ],
];
