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

    'oidc_heading' => 'Authentification unique OIDC',
    'oidc_hint' => 'Connexion via OpenID Connect (Entra ID, Keycloak, Google …). La liaison de compte utilise uniquement issuer + subject ; le SSO ne crée jamais de comptes et n’attribue jamais de rôles. Après une connexion IdP, la vérification multifacteur relève du fournisseur d’identité.',
    'saml_heading' => 'SAML 2.0',
    'saml_hint' => 'Connexion initiée par le SP via SAML 2.0. Les assertions doivent être signées ; les réponses initiées par l’IdP (non sollicitées) sont rejetées. Un second certificat peut être enregistré en parallèle pour la rotation.',

    'break_glass_heading' => 'Comptes de secours (break-glass)',
    'break_glass_hint' => 'Comptes d’urgence non fédérés qui peuvent continuer à se connecter par mot de passe malgré le SSO obligatoire. Chaque utilisation est auditée. Conservez au moins un compte, sinon une panne de l’IdP bloque l’organisation.',
    'no_break_glass' => 'Aucun compte de secours défini.',
    'domains_heading' => 'Domaines e-mail',
    'domains_hint' => 'WorkDiary déduit l’organisation correspondante du domaine e-mail de connexion. Les domaines sont uniques au niveau global.',
    'no_domains' => 'Aucun domaine e-mail associé pour l’instant.',
    'domain_unverified' => 'non vérifié',
    'domain_dns_hint' => 'Créez un enregistrement TXT :name avec la valeur :value, puis choisissez « Vérifier ».',

    'provider' => [
        'custom' => 'Fournisseur OIDC personnalisé',
        'microsoft' => 'Microsoft 365',
        'google' => 'Google Workspace',
    ],

    'choose' => [
        'hint' => 'Plusieurs fournisseurs de connexion sont configurés pour :org. Veuillez choisir.',
    ],

    'discover' => [
        'hint' => 'Saisissez l’identifiant de votre organisation pour démarrer la connexion via votre fournisseur d’identité.',
        'org_label' => 'Identifiant de l’organisation',
        'org_placeholder' => 'p. ex. acme-sarl',
        'email_label' => 'Adresse e-mail',
        'email_placeholder' => 'p. ex. nom@entreprise.fr',
        'submit' => 'Continuer vers le fournisseur d’identité',
        'back_to_login' => 'Retour à la connexion',
    ],

    'protocol' => [
        'oidc' => 'OIDC',
        'saml' => 'SAML 2.0',
    ],

    'field' => [
        'label' => 'Libellé',
        'label_placeholder' => 'p. ex. Entra ID production',
        'tenant' => 'Annuaire (locataire)',
        'tenant_placeholder' => 'GUID du locataire ou domaine vérifié',
        'tenant_hint' => 'Spécifique au locataire — jamais common/organizations.',
        'tenant_keep' => 'laisser vide = inchangé',
        'domain' => 'Domaine e-mail',
        'domain_placeholder' => 'p. ex. entreprise.fr',
        'team_none' => '— aucune équipe —',
        'start_url' => 'URL de démarrage SSO',
        'callback_url' => 'URL de redirection/callback (à enregistrer chez l’IdP)',
        'acs_url' => 'URL ACS (à enregistrer chez l’IdP)',
        'metadata_url' => 'URL des métadonnées SP',
        'issuer' => 'Issuer',
        'client_id' => 'ID client',
        'client_secret' => 'Secret client',
        'secret_keep' => 'laisser vide = inchangé',
        'scopes' => 'Scopes',
        'idp_entity_id' => 'Entity ID de l’IdP',
        'idp_sso_url' => 'URL SSO de l’IdP',
        'idp_certificate' => 'Certificat de signature de l’IdP (PEM)',
        'idp_certificate_next' => 'Certificat successeur (rotation, optionnel)',
        'idp_certificate_next_hint' => 'Pendant la rotation, les deux certificats sont acceptés.',
        'active' => 'Actif',
        'enforced' => 'SSO obligatoire',
        'enforced_hint' => 'Bloque la connexion par mot de passe pour tous les comptes de cette organisation (sauf break-glass).',
        'email_link' => 'Liaison initiale par e-mail',
        'jit' => 'Créer les utilisateurs à la première connexion (JIT)',
        'jit_hint' => 'Crée un nouveau compte à la première connexion IdP (la limite de licence s’applique). Ne lie jamais des comptes existants — les collisions d’e-mail sont rejetées.',
        'jit_role' => 'Rôle JIT par défaut',
        'jit_role_none' => 'aucun rôle',
        'email_link_hint' => 'À la première connexion SSO, lier un compte existant via l’e-mail (uniquement en cas de correspondance unique). Ensuite, seuls issuer + subject comptent.',
        'private_network' => 'Autoriser un IdP sur réseau privé',
        'private_network_hint' => 'Exception à la protection SSRF pour les IdP on-premise (p. ex. Keycloak interne).',
        'break_glass_user' => 'Compte',
    ],

    'action' => [
        'issue' => 'Émettre',
        'revoke' => 'Révoquer',
        'save_mapping' => 'Enregistrer',
        'save_connection' => 'Enregistrer la connexion',
        'test_connection' => 'Tester la connexion',
        'remove_connection' => 'Supprimer la connexion',
        'break_glass_add' => 'Définir comme compte de secours',
        'break_glass_remove' => 'Retirer',
        'domain_add' => 'Ajouter un domaine',
        'domain_remove' => 'Supprimer',
        'domain_verify' => 'Vérifier',
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
        'inactive' => 'Inactif',
        'revoked' => 'Révoqué',
        'enforced' => 'SSO obligatoire',
    ],

    'flash' => [
        'token_issued' => 'Jeton SCIM émis.',
        'token_revoked' => 'Jeton SCIM révoqué.',
        'group_mapped' => 'Association d’équipe enregistrée.',
        'connection_saved' => 'Connexion :protocol enregistrée.',
        'connection_ok' => 'Connexion :protocol vérifiée avec succès.',
        'connection_removed' => 'Connexion supprimée.',
        'break_glass_added' => 'Compte de secours défini.',
        'break_glass_removed' => 'Statut de secours retiré.',
        'domain_added' => 'Domaine e-mail ajouté.',
        'domain_added_unverified' => 'Domaine ajouté — pas encore actif. Pour le prouver, créez un enregistrement TXT :name avec la valeur :value, puis choisissez « Vérifier ».',
        'domain_verified' => 'Domaine vérifié.',
        'domain_removed' => 'Domaine e-mail supprimé.',
    ],

    'error' => [
        'discovery_failed' => 'La découverte OIDC du fournisseur d’identité est injoignable ou incomplète.',
        'issuer_mismatch' => 'L’issuer de la réponse de découverte ne correspond pas à la configuration.',
        'token_exchange_failed' => 'L’échange de code avec le fournisseur d’identité a échoué.',
        'token_invalid' => 'Le jeton de connexion du fournisseur d’identité est invalide.',
        'token_expired' => 'Le jeton de connexion du fournisseur d’identité a expiré.',
        'jwks_failed' => 'Les clés de signature du fournisseur d’identité n’ont pas pu être chargées.',
        'no_account' => 'Aucun compte WorkDiary n’est lié à cette identité. Veuillez contacter votre administration.',
        'org_without_sso' => 'Aucune authentification unique n’est configurée pour cet identifiant.',
        'email_without_sso' => 'Aucune authentification unique n’est configurée pour ce domaine e-mail.',
        'tenant_required' => 'Microsoft 365 nécessite l’annuaire (locataire).',
        'google_issuer_invalid' => 'Pour Google Workspace, seul l’émetteur officiel https://accounts.google.com est autorisé.',
        'domain_invalid' => 'Veuillez indiquer un domaine e-mail valide.',
        'domain_taken' => 'Ce domaine e-mail est déjà associé à une autre organisation.',
        'domain_not_verified' => 'Aucun enregistrement TXT correspondant. Attendu : :name avec la valeur :value (les modifications DNS peuvent prendre quelques minutes).',
        'flow_expired' => 'La connexion SSO a expiré. Veuillez réessayer.',
        'module_disabled' => 'L’authentification unique n’est pas disponible pour cette organisation.',
        'url_not_public' => 'L’URL n’est pas accessible publiquement. Pour les fournisseurs internes, activez « Autoriser un IdP sur réseau privé ».',
        'entra_issuer_not_tenant_specific' => 'Microsoft Entra ID exige l’issuer spécifique au tenant (https://login.microsoftonline.com/<GUID-du-tenant>/v2.0) — jamais common/organizations.',
        'entra_email_link_forbidden' => 'La liaison initiale par e-mail est bloquée pour Microsoft Entra ID : son claim e-mail n’est pas vérifié (attaque nOAuth). Provisionnez les identités à l’avance (SCIM/manuel) ou utilisez le JIT.',
        'saml_invalid' => 'La réponse SAML est invalide.',
        'saml_unsolicited' => 'Les réponses SAML non sollicitées (initiées par l’IdP) sont rejetées. Veuillez démarrer la connexion depuis WorkDiary.',
        'saml_no_nameid' => 'La réponse SAML ne contient pas de NameID. Configurez une règle de claim NameID chez l’IdP (p. ex. ADFS).',
        'saml_settings_invalid' => 'La configuration SAML est incomplète ou invalide.',
        'saml_certificate_invalid' => 'Le certificat de l’IdP n’a pas pu être lu (PEM attendu).',
    ],
];
