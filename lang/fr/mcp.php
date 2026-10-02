<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mcp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// MCP-Server für KI-Assistenten (MVP-1063/1064).
return [
    'error' => [
        'forbidden' => 'Pas d’accès à cet outil.',
        'scope' => 'Ce jeton n’est pas autorisé pour les assistants IA (MCP). Créez un jeton avec le droit « Assistant IA (MCP) ».',
        'not_found' => 'Introuvable.',
        'invalid_number' => 'Ligne :position : la quantité, le prix ou le taux de TVA n’est pas un nombre.',
        'conflicts' => 'Non déplacé — conflits : :list',
    ],
    'oauth' => [
        'title' => 'Connecter un assistant IA',
        'intro' => ':client souhaite accéder aux données de :organization dans workDiary au nom de :user.',
        'scope' => [
            'read' => 'Lire : clients, projets, commandes, devis, factures, postes ouverts, temps, rendez-vous, indicateurs et recherche — avec vos droits.',
            'write' => 'Créer des brouillons : devis et factures en brouillon, créer des clients, déplacer des interventions. L’émission et l’envoi restent entre vos mains.',
        ],
        'target' => 'Après votre accord, l’accès est accordé à',
        'untrusted_warning' => 'Cette cible n’appartient à aucun assistant IA connu. N’acceptez que si vous venez de configurer vous-même la connexion — sinon un tiers accède à vos données.',
        'untrusted_confirm' => 'J’ai configuré moi-même la connexion à :target.',
        'untrusted_required' => 'Veuillez confirmer que vous avez configuré vous-même la connexion à cette cible.',
        'revoke_hint' => 'Vous pouvez révoquer l’accès à tout moment sous Profil → Jetons API.',
        'approve' => 'Autoriser l’accès',
        'deny' => 'Refuser',
        'error' => [
            'title' => 'Connexion impossible',
            'client' => 'Client inconnu — veuillez reconfigurer le connecteur.',
            'redirect_uri' => 'L’adresse de retour n’est pas enregistrée pour ce client ou n’est pas autorisée (uniquement https ou adresses locales).',
            'response_type' => 'Seul le type de réponse « code » est pris en charge.',
            'pkce' => 'PKCE avec S256 est requis.',
            'resource' => 'La ressource demandée n’est pas ce serveur MCP.',
            'scope' => 'Aucune autorisation valide demandée (mcp:read, mcp:write).',
            'grant' => 'Le code ou le jeton d’actualisation est invalide, expiré ou déjà utilisé.',
            'grant_type' => 'Ce type d’octroi n’est pas pris en charge.',
            'disabled' => 'Votre organisation n’a pas autorisé les assistants IA (MCP). L’administration l’active dans les paramètres de l’organisation.',
            'no_organization' => 'Votre compte n’appartient à aucune organisation.',
        ],
    ],
];
