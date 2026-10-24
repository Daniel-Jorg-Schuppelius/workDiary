<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : redmine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Importe les tickets de projets Redmine sélectionnés comme tâches dans des projets WorkDiary et, si souhaité, les saisies de temps comme temps réservés, en lecture seule : titre, description, statut via « fermé » et échéance. Les conflits et cas à vérifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Instance',
    'task_link' => 'Ouvrir dans Redmine',
    'health' => [
        'not_configured' => 'Redmine n’est pas configuré (URL de base ou clé d’API manquante).',
        'not_connected' => 'Connexion à Redmine pas encore vérifiée.',
    ],
    'settings' => [
        'base_url' => 'URL de base',
        'base_url_help' => 'Adresse de l’instance Redmine, p. ex. https://redmine.example.com.',
        'api_key' => 'Clé d’API',
        'api_key_help' => 'Dans Redmine sous Mon compte → Clé d’accès API ; l’API REST doit être activée dans l’administration. Le compte a besoin, dans les projets, d’un rôle autorisé à voir les tickets et le temps passé, avec la visibilité « tous ».',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une URL de base avec une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
        'default_billable' => 'Importer les temps comme facturables',
        'default_billable_help' => 'Désactivé, les temps importés ne sont jamais facturables ; activé, le réglage du projet cible s’applique.',
        'time_window_days' => 'Fenêtre de temps (jours)',
        'time_window_days_help' => 'Combien de jours en arrière chaque exécution lit les saisies de temps. Les plus anciennes restent inchangées.',
    ],
    'time' => [
        'fallback' => 'Saisie de temps Redmine',
    ],
    'flash' => [
        'times_enabled' => 'Import des temps activé pour cette association.',
        'times_disabled' => 'Import des temps désactivé pour cette association. Les temps déjà importés sont conservés.',
        'user_invalid' => 'Veuillez choisir un utilisateur de votre organisation.',
        'user_mapped' => 'Utilisateur associé. La prochaine synchronisation réserve les temps en attente.',
        'user_unmapped' => 'Association supprimée. Les temps déjà réservés sont conservés.',
    ],
    'times' => [
        'title' => 'Saisies de temps',
        'hint' => 'Pour chaque association, les saisies de temps Redmine des :days derniers jours peuvent être importées. Redmine ne connaît que le jour et les heures ; elles sont donc réservées comme durée sans heure dans le projet cible ; la tâche est celle du ticket lié.',
        'col' => [
            'project' => 'Projet Redmine',
            'last_run' => 'Dernier import des temps',
            'import' => 'Importer les temps',
        ],
        'counters' => ':created nouveaux · :updated modifiés · :unresolved_users en attente d’utilisateur · :removed supprimés',
        'enabled' => 'Activé',
        'disabled' => 'Désactivé',
        'empty' => 'Aucune association de projet',
    ],
    'users' => [
        'title' => 'Association des utilisateurs',
        'hint' => 'Les saisies de temps ne sont réservées que pour des utilisateurs Redmine associés, jamais en silence pour quelqu’un d’autre. La prochaine synchronisation réserve les saisies en attente après l’association.',
        'waiting' => ':name (:count saisies en attente)',
        'map' => 'Associer',
        'none_pending' => 'Aucune saisie de temps n’attend d’association d’utilisateur',
        'mapped' => 'Associés',
        'mapping' => 'Utilisateur Redmine #:redmine → :user',
        'unmap' => 'Supprimer l’association',
        'confirm_unmap' => 'Supprimer l’association ? Les temps déjà réservés sont conservés, les nouvelles saisies attendent de nouveau.',
    ],
];
