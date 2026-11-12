<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : open-issue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Punti aperti',
        'show' => 'Punto aperto :title',
        'history' => 'Cronologia',
    ],
    'field' => [
        'ticket' => 'Ticket',
        'title' => 'Titolo',
        'description' => 'Descrizione',
        'category' => 'Categoria',
        'severity' => 'Gravità',
        'status' => 'Stato',
        'assignee' => 'Assegnato a',
        'creator' => 'Creato da',
        'due_at' => 'Scadenza',
        'visibility' => 'Visibilità',
        'closed_at' => 'Chiuso il',
        'closed_by' => 'Chiuso da',
        'reason' => 'Motivo',
        'resolution' => 'Soluzione',
        'follow_up' => 'Incarico di follow-up',
    ],
    'action' => [
        'details' => 'Dettagli',
        'openTicket' => 'Apri ticket',
        'create' => 'Crea punto aperto',
        'edit' => 'Modifica',
        'assign' => 'Assegna',
        'start' => 'Metti in corso',
        'block' => 'Blocca',
        'unblock' => 'Sblocca',
        'complete' => 'Completa',
        'wontDo' => 'Non verrà fatto',
        'reopen' => 'Riapri',
        'delete' => 'Elimina',
        'publishToCustomer' => 'Condividi con il cliente',
        'followUp' => 'Crea incarico di follow-up',
    ],
    'flash' => [
        'ticketOpened' => 'Il ticket :no è stato aperto dal punto aperto.',
        'ticketExists' => 'Per questo punto aperto esiste già il ticket :no.',
        'created' => 'Punto aperto creato.',
        'updated' => 'Punto aperto aggiornato.',
        'deleted' => 'Punto aperto eliminato.',
        'assigned' => 'Assegnazione aggiornata.',
        'status' => [
            'open' => 'Punto aperto riaperto.',
            'inProgress' => 'Il punto aperto è ora in corso.',
            'blocked' => 'Il punto aperto è stato bloccato.',
            'done' => 'Il punto aperto è stato completato.',
            'wontDo' => 'Punto aperto contrassegnato come «non verrà fatto».',
            'reopened' => 'Il punto aperto è stato riaperto.',
        ],
    ],

    'follow_up' => [
        'content_intro' => 'Incarico di follow-up per il punto aperto #:id «:title»',
    ],
    'hint' => [
        'edit_tracked' => 'Le modifiche a scadenza, gravità e visibilità sono riportate nella cronologia del punto.',
    ],

    'filter' => [
        'all_statuses' => 'Tutti gli stati',
        'all_severities' => 'Tutte le gravità',
        'all_assignees' => 'Tutti i responsabili',
        'no_match' => 'Nessun punto aperto corrisponde al filtro.',
    ],
];
