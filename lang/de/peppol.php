<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : peppol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Sendet und empfängt Belege über einen zertifizierten Peppol-Access-Point-Provider. WorkDiary betreibt selbst keinen Access Point — Endpunkte und Feldnamen des Providers werden hier konfiguriert.',
    ],
    'settings' => [
        'base_url' => 'Basis-URL des Providers',
        'base_url_help' => 'Wurzel der Provider-API, z. B. https://api.example-ap.eu/v1 — ohne abschließenden Schrägstrich.',
        'api_key' => 'Zugangsschlüssel',
        'api_key_help' => 'Wird verschlüsselt gespeichert und in Protokollen geschwärzt.',
        'auth_header' => 'Auth-Header',
        'auth_header_help' => 'Kopfzeile, in der der Schlüssel mitgeschickt wird (Standard: Authorization).',
        'auth_scheme' => 'Auth-Präfix',
        'auth_scheme_help' => 'Vorangestelltes Schema, z. B. Bearer. Leer lassen, wenn der Provider den reinen Schlüssel erwartet.',
        'send_path' => 'Sende-Endpunkt (Pfad)',
        'receive_path' => 'Abhol-Endpunkt (Pfad)',
        'ack_path' => 'Quittungs-Endpunkt (Pfad)',
        'ack_path_help' => 'Der Platzhalter {messageId} wird durch die Nachrichtenkennung ersetzt; ohne Platzhalter geht die Kennung im Body mit.',
        'health_path' => 'Status-Endpunkt (Pfad)',
        'payload_field' => 'Feldname des Umschlags',
        'payload_field_help' => 'JSON-Feld, in dem der SBDH-Umschlag steht. Leer lassen, wenn der Provider rohes XML als Body erwartet.',
        'message_id_field' => 'Feldname der Nachrichtenkennung',
        'status_field' => 'Feldname des Transportstatus',
        'items_field' => 'Feldname der Eingangsliste',
        'sender_participant_id' => 'Eigene Peppol-Teilnehmer-ID',
        'sender_participant_id_help' => 'Form <ICD>:<Kennung>, z. B. 9930:DE123456789. Muss beim Provider auf diese Organisation registriert sein.',
        'sender_country' => 'Absenderland',
        'sender_country_help' => 'Zwei Buchstaben (ISO 3166-1), wird als COUNTRY_C1 in den Umschlag geschrieben.',
        'sml_zone' => 'SML-Zone',
        'sml_zone_help' => 'Produktion oder Test. Die NAPTR-Zonen sind das aktuelle Verfahren; die CNAME-Zonen bestehen nur noch aus der Migration.',
        'lookup_ttl_hours' => 'Gültigkeit der Teilnehmerprüfung (Stunden)',
        'lookup_ttl_hours_help' => 'So lange gilt ein SMP-Ergebnis, bevor erneut aufgelöst wird. 0 = jedes Mal neu auflösen.',
    ],
    'health' => [
        'not_configured' => 'Keine Zugangsdaten zum Access-Point-Provider hinterlegt.',
        'sender_invalid' => 'Die eigene Peppol-Teilnehmer-ID fehlt oder hat nicht die Form <ICD>:<Kennung>.',
        'unreachable' => 'Der Access-Point-Provider antwortet nicht oder lehnt den Zugangsschlüssel ab.',
        'ok' => 'Verbunden mit :url.',
    ],
    'field' => [
        'participant_id' => 'Peppol-Teilnehmer-ID',
        'participant_id_hint' => 'Form <ICD>:<Kennung>, z. B. 9930:DE123456789 (USt-IdNr.) oder 0204:991-12345-67 (Leitweg-ID). Leer = kein Peppol-Versand an diesen Kunden.',
    ],
    'action' => [
        'send' => 'Per Peppol senden',
        'send_title' => 'Rechnung über den Access-Point-Provider zustellen — Zugangsnachweis ist die Transportquittung.',
        'check' => 'Peppol-Registrierung prüfen',
    ],
    'validator' => [
        'scope' => 'Geprüft wurde eine Teilmenge der Peppol-BIS-Billing-3.0-Regeln (:scenario) — das ist ausdrücklich kein Vollkonformitätsnachweis. Die vollständige Schematron-Prüfung leisten der KoSIT-Validator und der Access Point.',
    ],
    'error' => [
        'not_configured' => 'Für diese Organisation ist kein Peppol-Access-Point konfiguriert (Plugin „Peppol Access Point").',
        'sender_invalid' => 'Die eigene Peppol-Teilnehmer-ID fehlt oder ist ungültig — sie steht in den Plugin-Einstellungen.',
        'no_participant' => 'Für :customer ist keine Peppol-Teilnehmer-ID hinterlegt.',
        'invalid_participant' => 'Die Peppol-Teilnehmer-ID von :customer ist ungültig: :value',
        'not_registered' => 'Der Empfänger :participant ist in Peppol nicht registriert.',
        'unsupported_document' => 'Der Empfänger :participant nimmt das Format :document über Peppol nicht an.',
        'lookup_failed' => 'Die Peppol-Teilnehmerauflösung ist fehlgeschlagen: :message',
        'validation' => 'Die Rechnung erfüllt die geprüften Peppol-Regeln nicht: :messages',
        'transport' => 'Der Access Point hat den Versand nicht angenommen: :message',
        'not_issued' => 'Nur gestellte Rechnungen lassen sich über Peppol zustellen.',
        'external_billing' => 'Die Fakturierung liegt bei einem externen System — WorkDiary stellt für diesen Kunden keine Rechnung zu.',
        'proforma' => 'Pro-forma-Rechnungen sind keine E-Rechnungen und gehen nicht über Peppol.',
    ],
    'status' => [
        'registered' => 'In Peppol registriert (SMP :smp, :count Dokumentformate).',
        'not_registered' => 'In Peppol nicht registriert.',
        'checked_at' => 'Zuletzt geprüft: :at',
        'never_checked' => 'Noch nicht geprüft.',
    ],
    'flash' => [
        'sent' => 'Rechnung an :participant übergeben (Nachricht :message, Transportstatus :status).',
        'checked' => 'Peppol-Prüfung für :customer: :result',
    ],
    'inbound' => [
        'summary' => 'Peppol-Eingang: :fetched abgeholt, :imported übernommen, :duplicates Dubletten, :unreadable nicht lesbar.',
        'document_name' => 'peppol-:id.xml',
    ],
];
