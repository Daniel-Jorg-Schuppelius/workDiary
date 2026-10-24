<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : prestashop.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Lit les commandes d’une boutique PrestaShop via le webservice, les reprend dans WorkDiary et rattache les acheteurs à des clients existants ; les acheteurs incertains deviennent des cas à clarifier. Rien n’est écrit dans la boutique.',
    ],
    'settings' => [
        'base_url' => 'Adresse de la boutique',
        'base_url_help' => 'Adresse de la boutique, p. ex. https://shop.example.com (sans /api).',
        'api_key' => 'Clé du webservice',
        'api_key_help' => 'Dans PrestaShop, activez le webservice sous Paramètres avancés → Webservice et créez une clé avec droit de lecture (GET) sur orders, customers, currencies et order_states ; pour les remboursements aussi order_slip, pour le fuseau horaire configurations.',
        'shop_timezone' => 'Fuseau horaire de la boutique',
        'shop_timezone_help' => 'PrestaShop fournit les heures sans fuseau horaire. Laissez vide pour utiliser le fuseau lu lors de la vérification de la connexion (droit configurations), sinon celui de votre organisation ; ou saisissez p. ex. Europe/Vienna.',
    ],
    'search' => [
        'domain' => 'Commandes PrestaShop',
    ],
    'cockpit' => [
        'title' => 'Commandes PrestaShop',
    ],
    'health' => [
        'not_configured' => 'PrestaShop n’est pas configuré (adresse de la boutique ou clé manquante).',
        'not_connected' => 'La connexion PrestaShop n’a pas encore été vérifiée.',
    ],
];
