<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : domain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'health' => [
        'error' => 'Le contrôle de santé a échoué (:class).',
        'no_org_context' => 'Aucun contexte d\'organisation.',
        'ok' => 'Connexions DomainReselling correctes.',
        'pilot_open' => 'Connexion active, pilote réel encore en attente.',
    ],
    'plugin' => [
        'description' => 'Connecter et gérer de manière contrôlée les domaines d\'un compte DomainReselling avec les clients.',
    ],
    'settings' => [
        'connection_note' => 'Les identifiants (login/mot de passe) sont gérés par connexion — pas ici.',
        'connection_note_link' => 'Vers les connexions DomainReselling',
        'timeout' => 'Timeout API (secondes)',
        'timeout_help' => 'Temps d\'attente maximal par requête au fournisseur. Par défaut : 20.',
        'check_budget_per_hour' => 'Budget de vérification par heure',
        'check_budget_per_hour_help' => 'Nombre maximal de vérifications de disponibilité par organisation et par heure — protège contre les points de pénalité du fournisseur. Par défaut : 300.',
        'check_cache_ttl' => 'Cache de vérification (secondes)',
        'check_cache_ttl_help' => 'Durée de mise en cache d\'un résultat de disponibilité ; les résultats en cache ne consomment pas de budget. Par défaut : 300.',
        'list_page_size' => 'Taille de page des listes',
        'list_page_size_help' => 'Taille de lot des requêtes paginées de listes de domaines lors de la synchronisation. Par défaut : 100.',
        'stale_after_hours' => 'Obsolète après (heures)',
        'stale_after_hours_help' => 'Âge des données à partir duquel une projection de domaine est marquée comme obsolète. Par défaut : 24.',
        'range_error' => 'Veuillez saisir un nombre entier entre :min et :max.',
    ],
];
