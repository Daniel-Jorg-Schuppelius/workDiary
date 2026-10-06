<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ebics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// EBICS-Bankzugang (MVP-124).
return [
    'title' => 'EBICS-Bankzugang',
    'section' => [
        'access' => 'Zugangsdaten der Bank',
        'steps' => 'Einrichtung',
        'journal' => 'Verlauf',
    ],
    'field' => [
        'host_url' => 'EBICS-URL der Bank',
        'ebics_host' => 'Host-ID',
        'ebics_partner' => 'Kunden-ID (Partner-ID)',
        'ebics_user' => 'Teilnehmer-ID (User-ID)',
    ],
    'hint' => [
        'host_url' => 'Steht mit Host-, Kunden- und Teilnehmer-ID im EBICS-Zugangsbrief der Bank (EBICS 3.0).',
        'active' => 'Tagesauszüge holt der nächtliche Lauf; abgerufen bis :date.',
    ],
    'step' => [
        'keys' => 'Schlüssel erzeugen (Signatur, Authentifikation, Verschlüsselung).',
        'initialize' => 'Öffentliche Schlüssel an die Bank senden (INI und HIA).',
        'letter' => 'Initialisierungsbrief drucken, unterschreiben und an die Bank schicken.',
        'activate' => 'Nach der Freischaltung durch die Bank: Bankschlüssel abrufen.',
    ],
    'action' => [
        'save' => 'Speichern',
        'keys' => 'Schlüssel erzeugen',
        'initialize' => 'An die Bank senden',
        'letter' => 'Brief herunterladen',
        'activate' => 'Bankschlüssel abrufen',
        'fetch' => 'Auszüge jetzt abrufen',
        'suspend' => 'Zugang sperren',
        'submit' => 'Per EBICS einreichen',
        'confirm_not_submitted' => 'Als nicht eingereicht bestätigen',
    ],
    'confirm' => [
        'suspend' => 'Zugang bei der Bank sperren? Danach braucht es neue Schlüssel und einen neuen Brief.',
        'submit' => 'Zahllauf jetzt per EBICS an die Bank senden? Die Freigabe erteilen Sie anschließend bei der Bank.',
        'not_submitted' => 'Haben Sie bei der Bank geprüft, dass dieser Auftrag nicht vorliegt? Danach lässt sich der Zahllauf erneut einreichen.',
    ],
    'last_error' => 'Letzter Fehler: :error',
    'flash' => [
        'saved' => 'Zugangsdaten gespeichert.',
        'keys_created' => 'Schlüssel erzeugt.',
        'initialized' => 'Schlüssel an die Bank gesendet. Bitte den Initialisierungsbrief unterschreiben und einreichen.',
        'activated' => 'Bankschlüssel abgerufen — der Zugang ist freigeschaltet.',
        'suspended' => 'Zugang gesperrt.',
        'fetched' => ':statements Auszüge übernommen, :skipped bereits vorhanden.',
        'submitted' => 'Zahllauf eingereicht (Auftrag :order). Freigabe bitte bei der Bank erteilen.',
        'submission_released' => 'Übermittlung als nicht erfolgt vermerkt. Der Zahllauf lässt sich erneut einreichen.',
    ],
    'error' => [
        'host_not_allowed' => 'Diese Adresse ist als Bankzugang nicht zulässig.',
        'locked_after_keys' => 'Nach dem Erzeugen der Schlüssel lassen sich die Zugangsdaten nicht mehr ändern — erst den Zugang sperren.',
        'invalid_step' => 'Dieser Schritt passt nicht zum Stand der Einrichtung.',
        'not_initialized' => 'Den Brief gibt es erst, nachdem die Schlüssel an die Bank gesendet wurden.',
        'not_active' => 'Der EBICS-Zugang ist nicht freigeschaltet.',
        'no_keys' => 'Für diesen Zugang liegen keine Schlüssel vor.',
        'no_data' => 'Die Bank hält keine neuen Daten bereit.',
        'bank_rejected' => 'Die Bank hat den Auftrag abgelehnt.',
        'failed' => 'Die Verbindung zur Bank ist fehlgeschlagen.',
        'already_submitted' => 'Dieser Zahllauf wurde bereits per EBICS eingereicht.',
        'outcome_unclear' => 'Der Ausgang der letzten Übermittlung ist unklar. Bitte zuerst bei der Bank prüfen, ob der Auftrag vorliegt.',
    ],
    'letter' => [
        'title' => 'EBICS-Initialisierungsbrief (INI/HIA)',
        'sent_at' => 'Gesendet am',
        'key' => [
            'A' => 'Bankfachlicher Schlüssel (Signatur)',
            'X' => 'Authentifikationsschlüssel',
            'E' => 'Verschlüsselungsschlüssel',
        ],
        'hash' => 'Hashwert (SHA-256):',
        'certificate' => 'Zertifikat ausgestellt am :date',
        'confirmation' => 'Hiermit bestätige ich, dass die obigen Schlüssel an die Bank übermittelt wurden.',
        'place_date' => 'Ort, Datum',
        'signature' => 'Unterschrift des Teilnehmers',
        'printed_at' => 'Erstellt am :date',
    ],
    'run' => [
        'submitted' => 'Per EBICS eingereicht am :date (Auftrag :order).',
        'unclear' => 'Übermittlung per EBICS am :date begonnen — Ausgang unklar.',
    ],
];
