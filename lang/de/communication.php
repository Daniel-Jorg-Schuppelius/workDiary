<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : communication.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Kommunikation',
        'followups' => 'Offene Folgeaktionen',
    ],

    'field' => [
        'type' => 'Typ',
        'direction' => 'Richtung',
        'occurred_at' => 'Zeitpunkt',
        'subject' => 'Betreff',
        'body' => 'Inhalt / Verlauf',
        'result' => 'Ergebnis / Vereinbarung',
        'next_action' => 'Folgeaktion',
        'next_action_due_at' => 'Frist',
        'next_action_user' => 'Verantwortlich',
        'visibility' => 'Sichtbarkeit',
        'confidential' => 'Vertraulich',
        'customer_visible' => 'Für Kunden sichtbar',
        'participants' => 'Beteiligte',
        'participant_name' => 'Name',
        'participant_role' => 'Rolle',
        'participant_party' => 'Partei',
        'creator' => 'Erfasst von',
    ],

    'action' => [
        'create' => 'Notiz erfassen',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'delete' => 'Löschen',
        'publish' => 'Für Kunden freigeben',
        'mark_confidential' => 'Vertraulich markieren',
        'unmark_confidential' => 'Vertraulichkeit aufheben',
        'complete_followup' => 'Folgeaktion erledigt',
        'add_participant' => 'Beteiligten hinzufügen',
        'remove_participant' => 'Beteiligten entfernen',
    ],

    'flash' => [
        'created' => 'Kommunikationsnotiz wurde erfasst.',
        'updated' => 'Kommunikationsnotiz wurde aktualisiert.',
        'deleted' => 'Kommunikationsnotiz wurde gelöscht.',
        'published' => 'Notiz wurde für den Kunden freigegeben.',
        'confidential_set' => 'Notiz wurde als vertraulich markiert.',
        'confidential_unset' => 'Vertraulichkeit wurde aufgehoben.',
        'followup_completed' => 'Folgeaktion wurde als erledigt markiert.',
    ],

    'error' => [
        'internal_type_requires_internal_direction' => 'Interne Rücksprachen müssen die Richtung „Intern" haben.',
        'internal_direction_requires_internal_visibility' => 'Interne Kommunikation kann nicht für Kunden sichtbar sein.',
        'confidential_requires_internal_visibility' => 'Vertrauliche Notizen müssen intern bleiben.',
        'occurred_at_in_future' => 'Der Zeitpunkt darf nicht in der Zukunft liegen.',
        'due_before_occurrence' => 'Die Frist der Folgeaktion muss nach dem Kommunikationszeitpunkt liegen.',
        'unknown_type' => 'Unbekannter Kommunikationstyp.',
        'unknown_direction' => 'Unbekannte Richtung.',
        'confidential_not_publishable' => 'Vertrauliche Notizen können nicht für Kunden freigegeben werden.',
        'internal_not_publishable' => 'Interne Kommunikation kann nicht für Kunden freigegeben werden.',
        'no_followup' => 'Diese Notiz hat keine Folgeaktion.',
    ],

    'badge' => [
        'confidential' => 'Vertraulich',
        'followup_done' => 'Erledigt',
    ],

    'empty' => 'Noch keine Kommunikationsnotizen vorhanden.',
    'confirm_delete' => 'Kommunikationsnotiz wirklich löschen?',
    'confirm_publish' => 'Notiz wirklich für den Kunden sichtbar machen?',
];
