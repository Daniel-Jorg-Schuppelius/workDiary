<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_tasks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// To-Do-Sync (Feature 102, Schnitt E): Sektion im Msgraph-Admin-Panel + Flow-Flashes.
return [
    'heading' => 'Microsoft To Do synchronisieren',
    'intro' => 'Gleicht zugeordnete To-Do-Listen mit WorkDiary-Projekten ab (Todoist-Muster): 3-Wege-Abgleich, Konflikte in die Integrations-Inbox — nie Last-write-wins; remote Gelöschtes wird nur markiert.',
    'badge_connected' => 'Verbunden',
    'badge_inactive' => 'Getrennt',
    'account' => 'Verbundenes Konto',
    'connect' => 'To-Do-Sync verbinden',
    'disconnect' => 'To-Do-Sync trennen',
    'link' => [
        'list' => 'To-Do-Liste',
        'target' => 'Ziel',
        'project' => 'Projekt',
        'global' => 'Globales Kanban',
        'mode' => 'Richtung',
        'add' => 'Zuordnen',
        'remove' => 'Entfernen',
        'remove_confirm' => 'Zuordnung wirklich entfernen? Bereits synchronisierte Aufgaben und Referenzen bleiben erhalten.',
    ],
    'mode' => [
        'bidirectional' => 'Beide Richtungen',
        'todo_to_workdiary' => 'Nur To Do → WorkDiary',
        'workdiary_to_todo' => 'Nur WorkDiary → To Do',
    ],
    'flash' => [
        'not_configured' => 'Microsoft 365 ist nicht konfiguriert (MSGRAPH_CLIENT_ID/SECRET fehlen).',
        'state_invalid' => 'Der Anmeldevorgang ist abgelaufen oder ungültig — bitte erneut starten.',
        'oauth_denied' => 'Die Freigabe wurde abgebrochen.',
        'oauth_failed' => 'Die Verbindung ist fehlgeschlagen (:class).',
        'connected' => 'Microsoft To Do verbunden.',
        'disconnected' => 'To-Do-Sync getrennt — Zugriffstoken entfernt.',
        'no_connection' => 'Keine Microsoft-To-Do-Verbindung hergestellt.',
        'list_invalid' => 'Die gewählte To-Do-Liste ist nicht (mehr) verfügbar.',
        'project_invalid' => 'Das gewählte Projekt gehört nicht zu dieser Organisation.',
        'link_saved' => 'Listen-Zuordnung gespeichert.',
        'link_removed' => 'Listen-Zuordnung entfernt.',
    ],
];
