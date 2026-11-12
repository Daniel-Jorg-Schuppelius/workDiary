<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : open-issue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Offene Punkte',
        'show' => 'Offener Punkt :title',
        'history' => 'Verlauf',
    ],

    'field' => [
        'ticket' => 'Ticket',
        'title' => 'Titel',
        'description' => 'Beschreibung',
        'category' => 'Kategorie',
        'severity' => 'Schweregrad',
        'status' => 'Status',
        'assignee' => 'Zugewiesen an',
        'creator' => 'Angelegt von',
        'due_at' => 'Fällig bis',
        'visibility' => 'Sichtbarkeit',
        'closed_at' => 'Geschlossen am',
        'closed_by' => 'Geschlossen von',
        'reason' => 'Begründung',
        'resolution' => 'Lösung',
        'follow_up' => 'Folgeauftrag',
    ],

    'action' => [
        'details' => 'Details',
        'openTicket' => 'Ticket eröffnen',
        'create' => 'Offenen Punkt anlegen',
        'edit' => 'Bearbeiten',
        'assign' => 'Zuweisen',
        'start' => 'In Bearbeitung setzen',
        'block' => 'Blockieren',
        'unblock' => 'Entblocken',
        'complete' => 'Abschließen',
        'wontDo' => 'Wird nicht erledigt',
        'reopen' => 'Wiedereröffnen',
        'delete' => 'Löschen',
        'publishToCustomer' => 'Für Kunden freigeben',
        'followUp' => 'Folgeauftrag anlegen',
    ],

    'flash' => [
        'ticketOpened' => 'Ticket :no wurde aus dem offenen Punkt eröffnet.',
        'ticketExists' => 'Zu diesem offenen Punkt gibt es bereits das Ticket :no.',
        'created' => 'Offener Punkt wurde angelegt.',
        'updated' => 'Offener Punkt wurde aktualisiert.',
        'deleted' => 'Offener Punkt wurde gelöscht.',
        'assigned' => 'Zuweisung wurde aktualisiert.',
        'status' => [
            'open' => 'Offener Punkt wurde geöffnet.',
            'inProgress' => 'Offener Punkt ist jetzt in Bearbeitung.',
            'blocked' => 'Offener Punkt wurde blockiert.',
            'done' => 'Offener Punkt wurde abgeschlossen.',
            'wontDo' => 'Offener Punkt wird nicht erledigt.',
            'reopened' => 'Offener Punkt wurde wiedereröffnet.',
        ],
    ],

    'follow_up' => [
        'content_intro' => 'Folgeauftrag zu offenem Punkt #:id „:title“',
    ],
    'hint' => [
        'edit_tracked' => 'Änderungen an Frist, Schweregrad und Sichtbarkeit stehen im Verlauf des Punkts.',
    ],

    'filter' => [
        'all_statuses' => 'Alle Status',
        'all_severities' => 'Alle Schweregrade',
        'all_assignees' => 'Alle Zuständigen',
        'no_match' => 'Kein offener Punkt passt zum Filter.',
    ],
];
