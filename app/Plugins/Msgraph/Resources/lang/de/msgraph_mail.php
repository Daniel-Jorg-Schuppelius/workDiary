<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Graph-Mail-Versand (Feature 102): Mail-Sektion des Msgraph-Admin-Panels + Flow-Flashes.
return [
    'heading' => 'E-Mail-Versand über Microsoft 365',
    'intro' => 'Versendet WorkDiary-Mails (Rechnungen, Mahnungen, Benachrichtigungen) über Microsoft Graph statt SMTP — Modern Auth, kein Basic-Auth-SMTP nötig.',
    'badge_connected' => 'Verbunden',
    'badge_inactive' => 'Getrennt',
    'mailer_hint' => 'Der msgraph-Mailer ist derzeit nicht aktiv. Aktivierung über MAIL_MAILER=msgraph (oder eine failover-Kette mit msgraph) in der Installation.',
    'account' => 'Verbundenes Konto',
    'from_address' => 'Absenderadresse (optional)',
    'from_placeholder' => 'z. B. rechnung@firma.de (Shared Mailbox)',
    'from_hint' => 'Leer = das verbundene Konto sendet als es selbst. Eine abweichende Adresse braucht das Exchange-Recht „Senden als" und den Scope Mail.Send.Shared.',
    'save_to_sent' => 'Kopie im Gesendet-Ordner ablegen',
    'connect' => 'Mail-Versand verbinden',
    'disconnect' => 'Mail-Versand trennen',
    'flash' => [
        'not_configured' => 'Microsoft 365 ist nicht konfiguriert (MSGRAPH_CLIENT_ID/SECRET fehlen).',
        'state_invalid' => 'Der Anmeldevorgang ist abgelaufen oder ungültig — bitte erneut starten.',
        'oauth_denied' => 'Die Freigabe wurde abgebrochen.',
        'oauth_failed' => 'Die Verbindung ist fehlgeschlagen (:class).',
        'connected' => 'Mail-Versand über Microsoft 365 verbunden.',
        'disconnected' => 'Mail-Versand getrennt — Zugriffstoken entfernt.',
        'no_connection' => 'Keine Microsoft-365-Mail-Verbindung hergestellt.',
        'settings_saved' => 'Mail-Einstellungen gespeichert.',
        'test_sent' => 'Testnachricht an :to gesendet (über Microsoft Graph).',
        'test_failed' => 'Testversand fehlgeschlagen: :error',
        'test_no_recipient' => 'Keine Empfängeradresse — bitte eine E-Mail-Adresse angeben.',
    ],

    'test' => [
        'subject' => ':app — Test (Microsoft 365)',
        'body' => '<p>Diese Testnachricht wurde von :app über Microsoft Graph versendet.</p>',
        'recipient' => 'Empfänger (optional)',
        'recipient_placeholder' => 'Standard: verbundenes Konto',
        'hint' => 'Sendet direkt über die Graph-Verbindung — unabhängig von MAIL_MAILER.',
        'send' => 'Testmail senden',
    ],
];
