<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : todoist.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'subtitle' => 'Aufgaben-Synchronisation mit Todoist — nur ausdrücklich zugeordnete Projekte, Konflikte über die Integrations-Inbox.',
    'task_link' => 'In Todoist öffnen',

    'connection' => [
        'title' => 'Verbindung',
        'none' => 'Keine Todoist-Verbindung. Es wird genau eine Verbindung je Organisation hergestellt.',
        'privacy_note' => 'Mit der Verbindung werden Titel, Beschreibungen, Status, Fälligkeiten und Zuständige der zugeordneten Aufgaben an Todoist übertragen bzw. von dort gelesen. Lösch-Scopes werden nicht angefordert.',
        'connect' => 'Mit Todoist verbinden',
        'reconnect' => 'Verbindung erneuern',
        'disconnect' => 'Trennen',
        'confirm_disconnect' => 'Verbindung trennen? Zuordnungen und Referenzen bleiben erhalten.',
        'account' => 'Konto',
        'connected_at' => 'Verbunden seit',
        'last_sync' => 'Letzter Abgleich',
        'sync_now' => 'Jetzt abgleichen',
        'open_inbox' => 'Integrations-Inbox',
    ],

    'status' => [
        'active' => 'Aktiv',
        'paused' => 'Pausiert',
        'disconnected' => 'Getrennt',
    ],

    'links' => [
        'title' => 'Projektzuordnungen',
        'empty' => 'Noch keine Projektzuordnungen.',
        'add' => 'Zuordnen',
        'hint' => 'Neue Zuordnungen starten als Entwurf — Aktivierung erst nach dem Preflight (kein unbeaufsichtigter Vollimport).',
        'global_kanban' => 'Globales Kanban',
        'target_project' => 'WorkDiary-Projekt',
        'workdiary_project' => 'WorkDiary-Projekt',
        'preflight' => 'Preflight',
        'activate' => 'Aktivieren',
        'pause' => 'Pausieren',
        'remove' => 'Entfernen',
        'confirm_remove' => 'Zuordnung entfernen? Referenzen bleiben erhalten.',
        'col' => [
            'todoist_project' => 'Todoist-Projekt',
            'target' => 'Ziel',
            'mode' => 'Richtung',
            'last_run' => 'Letzter Lauf',
            'actions' => 'Aktionen',
        ],
    ],

    'mode' => [
        'todoist_to_workdiary' => 'Todoist → WorkDiary',
        'workdiary_to_todoist' => 'WorkDiary → Todoist',
        'bidirectional' => 'Bidirektional',
    ],

    'link_status' => [
        'draft' => 'Entwurf',
        'active' => 'Aktiv',
        'paused' => 'Pausiert',
    ],

    'preflight' => [
        'title' => 'Preflight',
        'counters' => 'Kennzahlen',
        'tasks' => 'Aktive Aufgaben',
        'subtasks' => 'Unteraufgaben',
        'recurring' => 'Wiederkehrend',
        'timed_due' => 'Fälligkeit mit Uhrzeit',
        'unassignable' => 'Nicht zuordenbare Bearbeiter',
        'referenced' => 'Bereits referenziert',
        'hint' => 'Wiederkehrende Aufgaben und Uhrzeit-Termine werden nur im Todoist-geführten Lesemodus übernommen. Standard ist „nur vorhandene zuordnen“.',
        'collaborators' => 'Bearbeiter-Zuordnung',
        'suggestion' => 'Vorschlag',
        'unassign' => '— Zuordnung lösen —',
        'no_collaborators' => 'Keine Kollaboratoren gefunden.',
        'sections' => 'Abschnitte → Status',
        'no_sections' => 'Dieses Projekt hat keine Abschnitte.',
        'section_unmapped' => '— nicht zugeordnet (Status unangetastet) —',
        'section_open' => 'Offen',
        'section_in_progress' => 'In Arbeit',
        'col' => [
            'collaborator' => 'Todoist-Kollaborator',
            'email' => 'E-Mail',
            'mapped' => 'Zugeordnet',
            'assign' => 'Zuordnen',
        ],
    ],

    'flash' => [
        'not_configured' => 'Todoist ist nicht konfiguriert (TODOIST_CLIENT_ID/SECRET fehlen).',
        'state_invalid' => 'Ungültiger oder abgelaufener OAuth-Status — bitte erneut verbinden.',
        'oauth_denied' => 'Die Autorisierung wurde abgebrochen.',
        'oauth_failed' => 'Token-Austausch fehlgeschlagen (:class).',
        'connected' => 'Todoist verbunden.',
        'disconnected' => 'Verbindung getrennt.',
        'link_saved' => 'Zuordnung gespeichert.',
        'link_removed' => 'Zuordnung entfernt.',
        'link_project_required' => 'Bitte ein WorkDiary-Projekt wählen.',
        'no_connection' => 'Keine aktive Todoist-Verbindung.',
        'sync_done' => 'Vollabgleich ausgeführt.',
        'preflight_failed' => 'Preflight fehlgeschlagen (:class).',
        'sections_saved' => 'Abschnitts-Zuordnungen gespeichert.',
        'collaborator_assigned' => 'Bearbeiter zugeordnet.',
        'collaborator_unassigned' => 'Zuordnung gelöst.',
        'collaborator_invalid' => 'Ungültiger Benutzer.',
    ],
];
