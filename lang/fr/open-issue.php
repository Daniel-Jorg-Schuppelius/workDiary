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
        'index' => 'Points ouverts',
        'show' => 'Point ouvert :title',
        'history' => 'Historique',
    ],
    'field' => [
        'ticket' => 'Ticket',
        'title' => 'Titre',
        'description' => 'Description',
        'category' => 'Catégorie',
        'severity' => 'Gravité',
        'status' => 'Statut',
        'assignee' => 'Assigné à',
        'creator' => 'Créé par',
        'due_at' => 'Échéance',
        'visibility' => 'Visibilité',
        'closed_at' => 'Clôturé le',
        'closed_by' => 'Clôturé par',
        'reason' => 'Motif',
        'resolution' => 'Résolution',
        'follow_up' => 'Mission de suivi',
    ],
    'action' => [
        'details' => 'Détails',
        'openTicket' => 'Ouvrir un ticket',
        'create' => 'Créer un point ouvert',
        'edit' => 'Modifier',
        'assign' => 'Assigner',
        'start' => 'Passer en cours',
        'block' => 'Bloquer',
        'unblock' => 'Débloquer',
        'complete' => 'Terminer',
        'wontDo' => 'Ne sera pas fait',
        'reopen' => 'Rouvrir',
        'delete' => 'Supprimer',
        'publishToCustomer' => 'Partager avec le client',
        'followUp' => 'Créer une mission de suivi',
    ],
    'flash' => [
        'ticketOpened' => 'Le ticket :no a été ouvert à partir du point ouvert.',
        'ticketExists' => 'Le ticket :no existe déjà pour ce point ouvert.',
        'created' => 'Point ouvert créé.',
        'updated' => 'Point ouvert mis à jour.',
        'deleted' => 'Point ouvert supprimé.',
        'assigned' => 'Attribution mise à jour.',
        'status' => [
            'open' => 'Point ouvert rouvert.',
            'inProgress' => 'Le point ouvert est maintenant en cours.',
            'blocked' => 'Le point ouvert a été bloqué.',
            'done' => 'Le point ouvert a été terminé.',
            'wontDo' => 'Point ouvert marqué « ne sera pas fait ».',
            'reopened' => 'Le point ouvert a été rouvert.',
        ],
    ],

    'follow_up' => [
        'content_intro' => 'Mission de suivi du point ouvert n° :id « :title »',
    ],
    'hint' => [
        'edit_tracked' => 'Les modifications de l\'échéance, de la gravité et de la visibilité figurent dans l\'historique du point.',
    ],

    'filter' => [
        'all_statuses' => 'Tous les statuts',
        'all_severities' => 'Toutes les gravités',
        'all_assignees' => 'Tous les responsables',
        'no_match' => 'Aucun point ouvert ne correspond au filtre.',
    ],
];
