<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : security.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Sécurité',
    ],

    'subtitle' => "Aperçu en lecture seule de l'état pertinent pour la sécurité : sessions actives, jetons API, intégrations externes, derniers exports et accès du support.",

    'scope' => [
        'label' => 'Périmètre',
        'platform' => "À l'échelle de la plateforme",
    ],

    'privacy_notice' => "Cette page n'affiche que des métadonnées. Les valeurs de jetons, les hachages, les secrets, les mots de passe ou le contenu des sessions ne sont jamais affichés. Toutes les données restent locales.",

    'deferred_notice' => "Les traitements automatisés de suppression et de conservation ne font pas partie de cet aperçu et suivront ultérieurement (Fonctionnalité 016, « Plus tard »).",

    'section' => [
        'advisories' => 'Posture de sécurité des dépendances',
        'sessions' => 'Sessions actives',
        'tokens' => 'Jetons API',
        'integrations' => 'Intégrations externes',
        'exports' => 'Derniers exports',
        'support_access' => 'Derniers accès du support',
        'two_factor' => 'Authentification à deux facteurs',
        'encryption' => 'Chiffrement (au repos)',
    ],

    'field' => [
        'severity' => 'Gravité',
        'package' => 'Paquet',
        'advisory' => 'Avis',
        'fixed_in' => 'Corrigé dans',
        'statement' => 'Évaluation (VEX)',
        'statement_placeholder' => 'p. ex. non exploitable — fonction non utilisée',
        'last_pull' => 'Dernière récupération',
        'user' => 'Utilisateur',
        'guest' => 'Non connecté',
        'ip' => 'Adresse IP',
        'user_agent' => 'Agent utilisateur',
        'last_activity' => 'Dernière activité',
        'sessions_total' => 'Sessions au total',
        'sessions_active' => 'Dont actives (< 2 h)',
        'token_name' => 'Nom',
        'abilities' => 'Permissions',
        'last_used_at' => 'Dernière utilisation',
        'expires_at' => 'Expire',
        'created_at' => 'Créé',
        'tokens_total' => 'Jetons au total',
        'plugins_active' => 'Plugins actifs',
        'external_references' => 'Références externes',
        'export_kind' => 'Type',
        'export_subject' => 'Objet',
        'format' => 'Format',
        'status' => 'Statut',
        'rows' => 'Enregistrements',
        'event' => 'Événement',
        'subject' => 'Objet',
        'users_total' => 'Utilisateurs au total',
        'users_with_2fa' => 'Avec 2FA active',
        'credentials' => 'Facteurs confirmés',
        'coverage' => 'Couverture',
        'encrypted_fields' => 'Champs chiffrés',
        'table' => 'Table',
        'fields' => 'Champs',
    ],

    'export' => [
        'kind' => [
            'data_transfer' => 'Transfert de données',
            'time' => 'Export des temps',
        ],
    ],

    'status' => [
        'active' => 'actif',
        'inactive' => 'inactif',
        'app_key_set' => 'APP_KEY définie',
        'app_key_missing' => 'APP_KEY manquante',
    ],

    'hint' => [
        'advisories' => 'Source : OSV.dev pour composer.lock/package-lock.json — récupération quotidienne (security:advisories-pull) ; évaluation (VEX) manuelle.',
        'sessions_driver' => "Pilote de session « :driver » — aucun aperçu en base de données possible. Seul le pilote « database » fournit une liste de sessions.",
        'tokens_no_secret' => "Seules les métadonnées sont affichées — jamais la valeur du jeton ni son hachage.",
        'support_access' => "Source : journal d'audit, préfixe d'événement « support. » (voir les principes d'accès du support).",
        'two_factor' => "Simple comptage des facteurs confirmés — aucun secret n'est lu.",
        'encryption' => "Ces champs sont chiffrés via « php artisan :command ». Le chiffrement dépend de l'APP_KEY.",
    ],

    'empty' => [
        'advisories' => 'Aucun avis de sécurité ouvert.',
        'sessions' => 'Aucune session trouvée.',
        'tokens' => 'Aucun jeton API actif.',
        'integrations' => 'Aucune intégration externe active.',
        'exports' => 'Aucun export enregistré pour le moment.',
        'support_access' => 'Aucun accès du support journalisé.',
    ],

    'generated_at' => 'Généré : :at',
    'action' => [
        'pull_advisories' => 'Récupérer maintenant',
    ],
];
