<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_tasks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Synchronisation To Do (Feature 102, tranche E) : section du panneau Msgraph + messages du flux.
return [
    'heading' => 'Synchroniser Microsoft To Do',
    'intro' => 'Synchronise les listes To Do liées avec les projets WorkDiary (modèle Todoist) : fusion à trois voies, les conflits vont dans la boîte d’intégration — jamais de last-write-wins ; les suppressions distantes sont seulement signalées.',
    'badge_connected' => 'Connecté',
    'badge_inactive' => 'Déconnecté',
    'account' => 'Compte connecté',
    'connect' => 'Connecter la synchronisation To Do',
    'disconnect' => 'Déconnecter la synchronisation To Do',
    'link' => [
        'list' => 'Liste To Do',
        'target' => 'Cible',
        'project' => 'Projet',
        'global' => 'Kanban global',
        'mode' => 'Direction',
        'add' => 'Lier',
        'remove' => 'Retirer',
        'remove_confirm' => 'Retirer vraiment cette liaison ? Les tâches et références déjà synchronisées sont conservées.',
    ],
    'mode' => [
        'bidirectional' => 'Les deux directions',
        'todo_to_workdiary' => 'Uniquement To Do → WorkDiary',
        'workdiary_to_todo' => 'Uniquement WorkDiary → To Do',
    ],
    'flash' => [
        'not_configured' => 'Microsoft 365 n’est pas configuré (MSGRAPH_CLIENT_ID/SECRET manquants).',
        'state_invalid' => 'Le processus de connexion a expiré ou est invalide — veuillez recommencer.',
        'oauth_denied' => 'L’autorisation a été annulée.',
        'oauth_failed' => 'La connexion a échoué (:class).',
        'connected' => 'Microsoft To Do connecté.',
        'disconnected' => 'Synchronisation To Do déconnectée — jetons d’accès supprimés.',
        'no_connection' => 'Aucune connexion Microsoft To Do établie.',
        'list_invalid' => 'La liste To Do sélectionnée n’est plus disponible.',
        'project_invalid' => 'Le projet sélectionné n’appartient pas à cette organisation.',
        'link_saved' => 'Liaison de liste enregistrée.',
        'link_removed' => 'Liaison de liste supprimée.',
    ],
];
