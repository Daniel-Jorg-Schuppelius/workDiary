<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : akaunting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend en lecture seule les factures et factures fournisseurs d’Akaunting dans le flux de documents, avec un lien vers l’original. Les fournisseurs sont rattachés via des rattachements existants ou leur nom.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse de l’installation Akaunting, p. ex. https://compta.example.com (sans /api).',
        'email' => 'E-mail',
        'email_help' => 'Identifiant d’un utilisateur Akaunting disposant du droit « lire l’API » et du droit de lecture sur les factures et les factures fournisseurs.',
        'password' => 'Mot de passe',
        'company_id' => 'ID de l’entreprise',
        'company_id_help' => 'Numéro de l’entreprise dans Akaunting ; il figure dans l’adresse de l’interface (p. ex. /1/…).',
        'pull_vouchers' => 'Récupérer les documents',
        'pull_vouchers_help' => 'Ce n’est qu’avec ce réglage que la récupération horaire lit les documents. Elle n’écrit rien dans Akaunting.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une installation de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
    'health' => [
        'not_configured' => 'Akaunting n’est pas configuré (adresse ou identifiants manquants).',
    ],
];
