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
