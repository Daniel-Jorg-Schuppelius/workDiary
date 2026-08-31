<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
        'attendances' => 'Stempelungen',
        'project_times' => 'Projektzeiten',
        // MVP-707 (Vollscan H20): Altsystem-Übernahme.
        'invoices' => 'Altrechnungen (OP-Stand)',
        'quotes' => 'Angebote',
        'assets' => 'Assets',
        'contact_persons' => 'Ansprechpartner',
        'documents' => 'Dokumente (ZIP)',
    ],

    'template' => [
        'example_required' => 'Beispielwert (Pflicht)',
        'example_optional' => 'Beispielwert (optional)',
        'download' => 'Mustervorlage herunterladen',
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
        'periodLocked' => 'Zeitraum gesperrt',
        'skipped' => 'Übersprungen',
        'blocked' => 'Fachlich gesperrt',
    ],

    'error' => [
        'email_taken' => 'Diese E-Mail-Adresse ist bereits vergeben.',
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
            'amount' => 'Kein gültiger Betrag.',
            'url' => 'Keine gültige Adresse (erwartet http:// oder https://).',
        ],
        'outOfRange' => [
            'rowLimit' => 'Maximale Zeilenanzahl (:max) überschritten — Rest wurde ignoriert.',
            'contactPersons' => 'Mehr als :max Ansprechpartner je Kunde/Lieferant sind nicht vorgesehen.',
        ],
        'fkMissing' => [
            'customer' => 'Kein Kunde mit Nummer :number gefunden.',
            'supplier' => 'Kein Lieferant mit Nummer :number gefunden.',
            'asset' => 'Kein Asset mit Nummer :number gefunden.',
            'article' => 'Kein Artikel mit Nummer :number gefunden.',
            'projectNumber' => 'Kein Projekt mit Nummer :number gefunden.',
            'customerName' => 'Kein eindeutiger Kunde mit Name „:value" gefunden.',
            'user' => 'Kein Benutzer mit E-Mail :value gefunden.',
            'project' => 'Kein Projekt „:value" gefunden — Zeile in die Zuordnungs-Inbox gelegt.',
        ],
        // MVP-707: Altsystem-Übernahme (Rechnungshoheit, Altrechnungen, Dokument-ZIP).
        'blocked' => [
            'invoiceSovereignty' => 'Rechnungshoheit liegt bei :program — lokale Altrechnungen sind für diesen Kunden gesperrt.',
        ],
        'invoice' => [
            'amountMissing' => 'Brutto- oder Nettobetrag (mit Steuersatz) fehlt.',
            'paidExceedsTotal' => 'Bezahlter Betrag (:paid) übersteigt den Rechnungsbetrag (:total).',
            'numberTaken' => 'Rechnungsnummer :number ist bereits vergeben.',
        ],
        'document' => [
            'manifestMissing' => 'Die ZIP-Datei enthält keine manifest.csv.',
            'fileMissing' => 'Datei „:file" ist im ZIP nicht enthalten.',
            'extension' => 'Dateiendung „:ext" ist nicht erlaubt.',
            'mime' => 'Dateiinhalt (:mime) ist nicht erlaubt.',
            'targetType' => 'Zieltyp muss customer, project oder asset sein.',
            'noContent' => 'Dokumente lassen sich nur über den ZIP-Import (manifest.csv + Dateien) übernehmen.',
            'zipUnreadable' => 'ZIP-Datei konnte nicht gelesen werden: :reason',
            'tooLarge' => 'Datei „:file" überschreitet das Größenlimit von :max MB.',
            'noActor' => 'Import-Lauf ohne auslösenden Benutzer — Dokumente brauchen einen Ersteller.',
        ],
        'persist' => [
            'noBookingUser' => 'Kein buchbarer Benutzer in der Organisation gefunden.',
        ],
        // MVP-438: GoBD-Sperre — kein stilles Überschreiben geprüfter Zeiträume.
        'periodLocked' => [
            'attendance' => 'Tag :date ist durch Tagesabschluss oder Monatsfreigabe gesperrt — Zeile übersprungen.',
            'projectTime' => 'Zeitraum :date ist bereits abgeschlossen/exportiert — Zeile übersprungen.',
        ],
        // MVP-438: iCal-Hinweiszeilen (bewusst konservatives Mapping).
        'ical' => [
            'allDay' => 'Ganztags-Termin „:event" übersprungen (nicht als Anwesenheit wertbar).',
            'noTime' => 'Termin „:event" ohne Uhrzeit übersprungen.',
            'category' => 'Termin „:event" außerhalb der Kategorie-Allowlist übersprungen.',
            'transparent' => 'Als „frei"/abwesend markierter Termin „:event" übersprungen.',
            'recurring' => 'Serientermin „:event": nur die Basisinstanz wurde importiert (Serien-Expansion folgt später).',
            'unsupportedEntity' => 'iCal-Import wird für diese Import-Art nicht unterstützt.',
        ],
    ],

    // MVP-707: Upload-Hinweise je Dateiart + Texte der Altrechnungs-Übernahme.
    'upload' => [
        'csv' => 'CSV-, Excel- oder iCal-Datei (.csv, .xlsx, .ics, max. :mb MB, :rows Zeilen)',
        'zip' => 'ZIP-Datei mit manifest.csv und den Dokumentdateien (.zip, max. :mb MB, :entries Dateien)',
        'zipHint' => 'Die manifest.csv (Vorlage oben) verweist je Zeile auf eine Datei im ZIP und ordnet sie Kunde, Projekt oder Asset zu.',
    ],
    'legacy' => [
        'position' => 'Übernahme aus Altsystem — Rechnung :number',
        'note' => 'Altrechnung aus :source übernommen (Eröffnungs-OP, keine Journalbuchung).',
    ],
];
