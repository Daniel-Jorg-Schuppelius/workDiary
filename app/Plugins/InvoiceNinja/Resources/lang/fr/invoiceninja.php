<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoiceninja.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend en lecture seule les factures, avoirs et dépenses d’Invoice Ninja dans le flux de documents, avec un lien vers l’original. Les clients et fournisseurs ne sont rattachés que via des rattachements existants, le numéro client ou le nom du fournisseur.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Laissez vide pour Invoice Ninja hébergé ; pour votre propre installation, son adresse, p. ex. https://factures.example.com.',
        'api_token' => 'Jeton API',
        'api_token_help' => 'Dans Invoice Ninja, créez-le sous Paramètres → Gestion du compte → Jetons API. Son utilisateur a besoin des droits d’administrateur ou du droit de voir les factures, avoirs et dépenses – sinon il ne voit que les siens. L’Invoice Ninja hébergé n’ouvre l’API que dans les offres payantes.',
        'pull_vouchers' => 'Récupérer les documents',
        'pull_vouchers_help' => 'Ce n’est qu’avec ce réglage que la récupération horaire lit les documents. Elle n’écrit rien dans Invoice Ninja.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une installation de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
    'health' => [
        'not_configured' => 'Invoice Ninja n’est pas configuré (jeton API manquant).',
    ],
];
