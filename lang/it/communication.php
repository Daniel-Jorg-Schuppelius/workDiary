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
        'index' => 'Comunicazione',
        'followups' => 'Azioni di follow-up aperte',
    ],

    'field' => [
        'type' => 'Tipo',
        'direction' => 'Direzione',
        'occurred_at' => 'Data e ora',
        'subject' => 'Oggetto',
        'body' => 'Contenuto / svolgimento',
        'result' => 'Risultato / accordo',
        'next_action' => 'Azione di follow-up',
        'next_action_due_at' => 'Scadenza',
        'next_action_user' => 'Responsabile',
        'visibility' => 'Visibilità',
        'confidential' => 'Riservato',
        'customer_visible' => 'Visibile al cliente',
        'participants' => 'Partecipanti',
        'participant_name' => 'Nome',
        'participant_role' => 'Ruolo',
        'participant_party' => 'Parte',
        'creator' => 'Registrato da',
    ],

    'action' => [
        'create' => 'Registra nota',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'delete' => 'Elimina',
        'publish' => 'Pubblica per il cliente',
        'mark_confidential' => 'Segna come riservato',
        'unmark_confidential' => 'Rimuovi riservatezza',
        'complete_followup' => 'Follow-up completato',
        'add_participant' => 'Aggiungi partecipante',
        'remove_participant' => 'Rimuovi partecipante',
    ],

    'flash' => [
        'created' => 'La nota di comunicazione è stata registrata.',
        'updated' => 'La nota di comunicazione è stata aggiornata.',
        'deleted' => 'La nota di comunicazione è stata eliminata.',
        'published' => 'La nota è stata pubblicata per il cliente.',
        'confidential_set' => 'La nota è stata contrassegnata come riservata.',
        'confidential_unset' => 'La riservatezza è stata rimossa.',
        'followup_completed' => 'L\'azione di follow-up è stata contrassegnata come completata.',
    ],

    'error' => [
        'internal_type_requires_internal_direction' => 'Le consultazioni interne devono usare la direzione «Interna».',
        'internal_direction_requires_internal_visibility' => 'La comunicazione interna non può essere visibile ai clienti.',
        'confidential_requires_internal_visibility' => 'Le note riservate devono rimanere interne.',
        'occurred_at_in_future' => 'La data non può essere nel futuro.',
        'due_before_occurrence' => 'La scadenza del follow-up deve essere successiva alla data della comunicazione.',
        'unknown_type' => 'Tipo di comunicazione sconosciuto.',
        'unknown_direction' => 'Direzione sconosciuta.',
        'confidential_not_publishable' => 'Le note riservate non possono essere pubblicate per i clienti.',
        'internal_not_publishable' => 'La comunicazione interna non può essere pubblicata per i clienti.',
        'no_followup' => 'Questa nota non ha un\'azione di follow-up.',
    ],

    'badge' => [
        'confidential' => 'Riservato',
        'followup_done' => 'Completato',
    ],

    'empty' => 'Nessuna nota di comunicazione presente.',
    'confirm_delete' => 'Eliminare davvero questa nota di comunicazione?',
    'confirm_publish' => 'Rendere davvero questa nota visibile al cliente?',
];
