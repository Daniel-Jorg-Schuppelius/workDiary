<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sso.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'SSO et services d\'annuaire',
    'intro' => 'Provisionnement SCIM 2.0 : votre fournisseur d\'identité (Entra ID, Keycloak, Okta …) crée, met à jour et désactive les comptes. Un compte désactivé ne peut plus se connecter immédiatement ; les rôles et les données métier restent dans WorkDiary. Authentification par un jeton bearer propre à l\'organisation.',
    'base_url' => 'URL de base SCIM',

    'new_token_heading' => 'Nouveau jeton',
    'new_token_hint' => 'Copiez-le maintenant — le texte en clair n\'est affiché qu\'une seule fois puis stocké uniquement sous forme de hachage.',

    'issue_heading' => 'Émettre un jeton',
    'tokens_heading' => 'Jetons émis',
    'no_tokens' => 'Aucun jeton émis pour le moment.',

    'groups_heading' => 'Groupes SCIM → équipe',
    'groups_hint' => 'Groupes provisionnés par le fournisseur d’identité. Associer un groupe à une équipe reflète ses membres dans WorkDiary (team_user) ; aucun rôle n’est jamais attribué.',
    'no_groups' => 'Aucun groupe SCIM provisionné pour le moment.',

    'field' => [
        'label' => 'Libellé',
        'label_placeholder' => 'p. ex. Entra ID production',
        'team_none' => '— aucune équipe —',
    ],

    'action' => [
        'issue' => 'Émettre',
        'revoke' => 'Révoquer',
        'save_mapping' => 'Enregistrer',
    ],

    'col' => [
        'status' => 'Statut',
        'last_used' => 'Dernière utilisation',
        'group' => 'Groupe',
        'members' => 'Membres',
        'team' => 'Équipe',
    ],

    'status' => [
        'active' => 'Actif',
        'revoked' => 'Révoqué',
    ],

    'flash' => [
        'token_issued' => 'Jeton SCIM émis.',
        'token_revoked' => 'Jeton SCIM révoqué.',
        'group_mapped' => 'Association d’équipe enregistrée.',
    ],
];
