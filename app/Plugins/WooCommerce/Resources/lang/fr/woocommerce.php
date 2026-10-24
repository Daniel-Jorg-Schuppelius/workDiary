<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : woocommerce.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Lit les commandes d’une boutique WooCommerce, les reprend dans WorkDiary et rattache les acheteurs à des clients existants ; les acheteurs incertains deviennent des cas à clarifier. Rien n’est écrit dans la boutique.',
    ],
    'settings' => [
        'base_url' => 'Adresse de la boutique',
        'base_url_help' => 'Adresse de la boutique WordPress en https, p. ex. https://shop.example.com (sans /wp-json).',
        'consumer_key' => 'Clé client (consumer key)',
        'consumer_key_help' => 'À créer dans WooCommerce sous Réglages → Avancé → API REST, avec un droit de lecture.',
        'consumer_secret' => 'Secret client (consumer secret)',
    ],
    'search' => [
        'domain' => 'Commandes WooCommerce',
    ],
    'cockpit' => [
        'title' => 'Commandes WooCommerce',
    ],
    'health' => [
        'not_configured' => 'WooCommerce n’est pas configuré (adresse de la boutique ou clés manquantes).',
        'not_connected' => 'La connexion WooCommerce n’a pas encore été vérifiée.',
    ],
];
