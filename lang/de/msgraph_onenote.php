<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_onenote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// OneNote-Übernahme (Feature 155, MVP-815): Sektion im Msgraph-Admin-Panel + Flow-Flashes.
return [
    'heading' => 'OneNote übernehmen',
    'intro' => 'Übernimmt ein OneNote-Notizbuch einmalig oder auf Anstoß als Notizen oder Wissensartikel — nur lesend (Notes.Read), kein Rückschreiben und kein laufender Abgleich. Notizbuch wird Sammlung, Abschnitte werden Untersammlungen.',
    'badge_connected' => 'Verbunden',
    'badge_disabled' => 'Ausgeschaltet',
    'account' => 'Verbundenes Konto',
    'connect' => 'OneNote verbinden',
    'disconnect' => 'OneNote trennen',
    'open_hub' => 'Zum Einstieg „Wissen“',
    'enable_hint' => 'Zuerst in den Plugin-Einstellungen „OneNote-Übernahme erlauben“ einschalten — erst dann fragt die Verbindung den zusätzlichen Bereich Notes.Read an.',
    'flash' => [
        'not_configured' => 'Microsoft 365 ist nicht konfiguriert (MSGRAPH_CLIENT_ID/SECRET fehlen).',
        'state_invalid' => 'Der Anmeldevorgang ist abgelaufen oder ungültig — bitte erneut starten.',
        'oauth_denied' => 'Die Freigabe wurde abgebrochen.',
        'oauth_failed' => 'Die Verbindung ist fehlgeschlagen (:class).',
        'connected' => 'OneNote verbunden.',
        'disconnected' => 'OneNote getrennt — Zugriffstoken entfernt.',
        'disabled' => 'Die OneNote-Übernahme ist in den Plugin-Einstellungen ausgeschaltet.',
    ],
];
