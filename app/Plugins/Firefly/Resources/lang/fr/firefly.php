<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : firefly.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend les entrées et sorties de Firefly III et les rattache aux factures ou factures entrantes via le numéro de pièce dans le libellé. Rien n’est comptabilisé dans WorkDiary et rien n’est écrit dans Firefly III.',
    ],
    'page' => [
        'subtitle' => 'Mouvements de compte de Firefly III, rattachés via les numéros de pièce du libellé — en lecture seule.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse de l’installation Firefly III, p. ex. https://finances.example.com (sans /api).',
        'access_token' => 'Jeton d’accès personnel',
        'access_token_help' => 'Créez un jeton d’accès personnel dans Firefly III sous Options → Profil → OAuth.',
        'window_days' => 'Période (jours)',
        'window_days_help' => 'Chaque synchronisation lit les mouvements de cette période jusqu’à aujourd’hui. 1 à 365 jours.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une installation de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
    'connection' => [
        'title' => 'Connexion',
        'connected' => 'Connecté',
        'not_connected' => 'Non connecté',
        'verify' => 'Vérifier la connexion',
        'settings' => 'Identifiants',
        'disconnect' => 'Déconnecter',
        'confirm_disconnect' => 'Déconnecter Firefly III ? La synchronisation s’arrête ; les mouvements repris restent jusqu’au prochain passage complet.',
        'sync_now' => 'Synchroniser maintenant',
        'not_configured' => 'Firefly III n’est pas configuré : saisissez l’adresse et le jeton dans les paramètres du plugin.',
        'privacy_note' => 'WorkDiary lit les entrées et sorties avec libellé et contrepartie et n’écrit rien dans Firefly III. WorkDiary n’en comptabilise aucun paiement.',
        'instance' => 'Instance',
        'account' => 'Compte',
        'window' => 'Période',
        'window_value' => 'Mouvements des :days derniers jours',
        'last_sync' => 'Dernière synchronisation',
        'incomplete' => 'incomplète',
        'last_error' => 'Dernière erreur',
    ],
    'list' => [
        'search' => 'Recherche',
        'search_placeholder' => 'Libellé, contrepartie, numéro de pièce',
        'all_matches' => 'Tous les rattachements',
        'col' => [
            'date' => 'Date',
            'kind' => 'Type',
            'counterparty' => 'Contrepartie',
            'description' => 'Libellé',
            'match' => 'Rattachement',
            'amount' => 'Montant',
        ],
        'empty' => 'Pas encore de mouvements',
    ],
    'search' => [
        'domain' => 'Mouvements Firefly III',
    ],
    'flash' => [
        'not_configured' => 'Firefly III n’est pas configuré : l’adresse ou le jeton manque dans les paramètres du plugin.',
        'verify_failed' => 'La connexion à Firefly III a échoué (:class).',
        'source_locked' => 'Les paramètres du plugin désignent une autre instance. Tant que des mouvements de la précédente sont repris, le changement n’est pas possible.',
        'verified' => 'Connexion Firefly III vérifiée : :source_name.',
        'not_ready' => 'Vérifiez d’abord la connexion.',
        'sync_failed' => 'La synchronisation a échoué (:class).',
        'synced' => 'Synchronisation : :read lus, :created nouveaux, :updated mis à jour, :matched rattachés, :removed supprimés.',
        'disconnected' => 'Firefly III déconnecté.',
    ],
    'health' => [
        'not_configured' => 'Firefly III n’est pas configuré (adresse ou jeton manquant).',
        'not_connected' => 'La connexion Firefly III n’a pas encore été vérifiée.',
    ],
];
