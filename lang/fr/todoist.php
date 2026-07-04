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
    'subtitle' => 'Synchronisation des tâches avec Todoist — uniquement les projets explicitement associés, conflits via la boîte de réception d\'intégration.',
    'task_link' => 'Ouvrir dans Todoist',

    'connection' => [
        'title' => 'Connexion',
        'none' => 'Aucune connexion Todoist. Une seule connexion est établie par organisation.',
        'privacy_note' => 'La connexion transmet à Todoist les titres, descriptions, statuts, échéances et responsables des tâches associées et les lit depuis Todoist. Les scopes de suppression ne sont pas demandés.',
        'connect' => 'Se connecter à Todoist',
        'reconnect' => 'Renouveler la connexion',
        'disconnect' => 'Déconnecter',
        'confirm_disconnect' => 'Déconnecter ? Les associations et références sont conservées.',
        'account' => 'Compte',
        'connected_at' => 'Connecté depuis',
        'last_sync' => 'Dernière synchronisation',
        'sync_now' => 'Synchroniser maintenant',
        'open_inbox' => "Boîte de réception d'intégration",
    ],

    'status' => [
        'active' => 'Active',
        'paused' => 'En pause',
        'disconnected' => 'Déconnectée',
    ],

    'links' => [
        'title' => 'Associations de projets',
        'empty' => 'Aucune association de projet.',
        'add' => 'Associer',
        'hint' => 'Les nouvelles associations démarrent en brouillon — activation uniquement après la préverification (pas d\'import complet non surveillé).',
        'global_kanban' => 'Kanban global',
        'target_project' => 'Projet WorkDiary',
        'workdiary_project' => 'Projet WorkDiary',
        'preflight' => 'Préverification',
        'activate' => 'Activer',
        'pause' => 'Mettre en pause',
        'remove' => 'Supprimer',
        'confirm_remove' => 'Supprimer l\'association ? Les références sont conservées.',
        'col' => [
            'todoist_project' => 'Projet Todoist',
            'target' => 'Cible',
            'mode' => 'Direction',
            'last_run' => 'Dernière exécution',
            'actions' => 'Actions',
        ],
    ],

    'mode' => [
        'todoist_to_workdiary' => 'Todoist → WorkDiary',
        'workdiary_to_todoist' => 'WorkDiary → Todoist',
        'bidirectional' => 'Bidirectionnel',
    ],

    'link_status' => [
        'draft' => 'Brouillon',
        'active' => 'Active',
        'paused' => 'En pause',
    ],

    'preflight' => [
        'title' => 'Préverification',
        'counters' => 'Indicateurs',
        'tasks' => 'Tâches actives',
        'subtasks' => 'Sous-tâches',
        'recurring' => 'Récurrentes',
        'timed_due' => 'Échéance avec heure',
        'unassignable' => 'Responsables non associables',
        'referenced' => 'Déjà référencées',
        'hint' => 'Les tâches récurrentes et les échéances horaires ne sont reprises qu\'en mode lecture piloté par Todoist. Par défaut : « associer uniquement l\'existant ».',
        'collaborators' => 'Association des responsables',
        'suggestion' => 'Suggestion',
        'unassign' => '— dissocier —',
        'no_collaborators' => 'Aucun collaborateur trouvé.',
        'sections' => 'Sections → statut',
        'no_sections' => 'Ce projet n\'a pas de sections.',
        'section_unmapped' => '— non associée (statut inchangé) —',
        'section_open' => 'Ouvert',
        'section_in_progress' => 'En cours',
        'col' => [
            'collaborator' => 'Collaborateur Todoist',
            'email' => 'E-mail',
            'mapped' => 'Associé',
            'assign' => 'Associer',
        ],
    ],

    'flash' => [
        'not_configured' => 'Todoist n\'est pas configuré (TODOIST_CLIENT_ID/SECRET manquants).',
        'state_invalid' => 'État OAuth non valide ou expiré — veuillez vous reconnecter.',
        'oauth_denied' => 'L\'autorisation a été annulée.',
        'oauth_failed' => 'Échec de l\'échange de jeton (:class).',
        'connected' => 'Todoist connecté.',
        'disconnected' => 'Connexion déconnectée.',
        'link_saved' => 'Association enregistrée.',
        'link_removed' => 'Association supprimée.',
        'link_project_required' => 'Veuillez choisir un projet WorkDiary.',
        'no_connection' => 'Aucune connexion Todoist active.',
        'sync_done' => 'Synchronisation complète effectuée.',
        'preflight_failed' => 'Échec de la préverification (:class).',
        'sections_saved' => 'Associations de sections enregistrées.',
        'collaborator_assigned' => 'Responsable associé.',
        'collaborator_unassigned' => 'Association supprimée.',
        'collaborator_invalid' => 'Utilisateur non valide.',
    ],
];
