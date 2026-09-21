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
        'notes' => 'Note',
        'note' => 'Nota',
    ],

    'field' => [

        'tags' => 'Tag',
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
        'storage' => 'Collocazione',
        'customer' => 'Cliente',
        'actions' => 'Azioni',
        'direction_choose' => 'Seleziona',
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
        'show' => 'Visualizza',
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
        'private_not_publishable' => 'Le note private restano a chi le ha scritte e non possono essere pubblicate.',
        'occurred_at_in_future' => 'La data non può essere nel futuro.',
        'due_before_occurrence' => 'La scadenza del follow-up deve essere successiva alla data della comunicazione.',
        'unknown_type' => 'Tipo di comunicazione sconosciuto.',
        'unknown_direction' => 'Direzione sconosciuta.',
        'confidential_not_publishable' => 'Le note riservate non possono essere pubblicate per i clienti.',
        'internal_not_publishable' => 'La comunicazione interna non può essere pubblicata per i clienti.',
        'no_followup' => 'Questa nota non ha un\'azione di follow-up.',
        'organization_note_not_publishable' => 'Le note interne dell\'organizzazione non possono essere condivise con i clienti.',
        'call_requires_external_direction' => 'Per una telefonata indichi se era in entrata o in uscita.',
        'direction_required' => 'Scelga una direzione.',
    ],

    'badge' => [
        'confidential' => 'Riservato',
        'followup_done' => 'Completato',
    ],

    'subtitle' => [
        'notes' => 'Annoti rapidamente e ritrovi le note – internamente o presso un cliente.',
    ],

    'storage' => [
        'all' => 'Tutte le collocazioni',
        'internal' => 'Interna',
        'customer' => 'Cliente',
    ],

    'filter' => [

        'all_tags' => 'Tutti i tag',
        'search' => 'Cerca',
        'search_placeholder' => 'Oggetto o contenuto …',
        'all_customers' => 'Tutti i clienti',
        'all_types' => 'Tutti i tipi',
        'open_followups' => 'Follow-up aperti',
    ],

    'hint' => [

        'tags' => 'Separi più tag con una virgola, ad es. manutenzione, riscaldamento.',
        'customer_not_published' => 'La nota compare nella scheda cliente, ma non nel portale clienti.',
    ],

    'section' => [
        'more' => 'Altri dettagli',
    ],

    'empty' => 'Nessuna nota di comunicazione presente.',
    'empty_filtered' => 'Nessuna nota trovata.',
    'confirm_delete' => 'Eliminare davvero questa nota di comunicazione?',
    'confirm_publish' => 'Rendere davvero questa nota visibile al cliente?',
    'convert' => [
        'action' => 'Converti in articolo della knowledge base',
        'error' => [
            'confidential' => 'Le note riservate non possono essere convertite in articolo della knowledge base.',
        ],
        'flash' => [
            'created' => 'Articolo creato come bozza; rimanda alla nota.',
            'existing' => 'Questa nota è già stata convertita.',
        ],
    ],
];
