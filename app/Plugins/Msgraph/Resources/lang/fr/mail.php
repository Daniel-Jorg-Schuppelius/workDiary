<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'flash' => [
        'msgraph_connection_required' => 'Une boîte Microsoft 365 nécessite d’abord la connexion d’envoi de mails dans le plugin Microsoft 365 (scope Mail.ReadWrite).',
    ],
    'error' => [
        'scope_missing' => 'Il manque l’autorisation :scope à la connexion Microsoft 365. Veuillez reconnecter l’envoi de mails dans le plugin Microsoft 365 – le droit Exchange « accès complet » ne suffit pas à lui seul. Si votre tenant interdit le consentement des utilisateurs, un administrateur doit d’abord relancer « Accorder pour l\'organisation ».',
    ],
    'transport' => [
        'msgraph' => 'Microsoft 365 (Graph)',
        'msgraph_hint' => 'Microsoft 365 : utilise la connexion mail Graph de l’organisation — pas d’identifiants IMAP nécessaires.',
    ],
];
