<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shopware.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Lit les commandes d’une boutique Shopware 6 via une intégration, les reprend dans WorkDiary et rattache les acheteurs à des clients existants ; les acheteurs incertains deviennent des cas à clarifier. Rien n’est écrit dans la boutique.',
    ],
    'settings' => [
        'base_url' => 'Adresse de la boutique',
        'base_url_help' => 'Adresse de la boutique, p. ex. https://shop.example.com (sans /api ni /admin).',
        'client_id' => 'ID de clé d’accès',
        'client_id_help' => 'Dans Shopware, créez une intégration sous Paramètres → Système → Intégrations. Elle a besoin du droit de lecture sur les commandes, les clients de commande, les clients, les lignes, les transactions de paiement, les devises et les états – ou de l’option « Administrateur ».',
        'client_secret' => 'Clé d’accès secrète',
    ],
    'search' => [
        'domain' => 'Commandes Shopware',
    ],
    'cockpit' => [
        'title' => 'Commandes Shopware',
    ],
    'health' => [
        'not_configured' => 'Shopware n’est pas configuré (adresse de la boutique ou intégration manquante).',
        'not_connected' => 'La connexion Shopware n’a pas encore été vérifiée.',
    ],
];
