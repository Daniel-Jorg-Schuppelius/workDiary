<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : peppol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'health' => [
        'not_configured' => 'Keine Zugangsdaten zum Access-Point-Provider hinterlegt.',
        'sender_invalid' => 'Die eigene Peppol-Teilnehmer-ID fehlt oder hat nicht die Form <ICD>:<Kennung>.',
        'unreachable' => 'Der Access-Point-Provider antwortet nicht oder lehnt den Zugangsschlüssel ab.',
        'ok' => 'Verbunden mit :url.',
    ],
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
];
