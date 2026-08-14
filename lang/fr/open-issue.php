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
    ],
    'field' => [
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
    ],
    'action' => [
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
    ],
    'flash' => [
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
];
