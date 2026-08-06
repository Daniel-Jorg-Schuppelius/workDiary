<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_contacts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Kontakt-Push (Feature 102, Schnitt D): Sektion im Msgraph-Admin-Panel + Kundenakten-Button.
return [
    'heading' => 'Kontakte nach Outlook übertragen',
    'intro' => 'Überträgt WorkDiary-Kunden auf Knopfdruck als Outlook-Kontakte des verbundenen Kontos (idempotent — kein Duplikat beim erneuten Übertragen).',
    'badge_connected' => 'Verbunden',
    'badge_inactive' => 'Getrennt',
    'account' => 'Verbundenes Konto',
    'connect' => 'Kontakt-Übertragung verbinden',
    'disconnect' => 'Kontakt-Übertragung trennen',
    'push_button' => 'Nach Outlook',
    'flash' => [
        'not_configured' => 'Microsoft 365 ist nicht konfiguriert (MSGRAPH_CLIENT_ID/SECRET fehlen).',
        'state_invalid' => 'Der Anmeldevorgang ist abgelaufen oder ungültig — bitte erneut starten.',
        'oauth_denied' => 'Die Freigabe wurde abgebrochen.',
        'oauth_failed' => 'Die Verbindung ist fehlgeschlagen (:class).',
        'connected' => 'Kontakt-Übertragung nach Outlook verbunden.',
        'disconnected' => 'Kontakt-Übertragung getrennt — Zugriffstoken entfernt.',
        'no_connection' => 'Keine Microsoft-365-Kontakt-Verbindung hergestellt.',
        'plugin_disabled' => 'Das Microsoft-365-Plugin ist nicht aktiviert.',
        'pushed' => 'Kunde als Outlook-Kontakt übertragen (ID :id).',
        'push_failed' => 'Übertragung fehlgeschlagen (:class).',
    ],
];
