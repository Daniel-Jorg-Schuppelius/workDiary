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
    'title' => 'SSO & directory services',
    'intro' => 'SCIM 2.0 provisioning: your identity provider (Entra ID, Keycloak, Okta …) creates, updates and deactivates accounts. A deactivated account can no longer sign in immediately; roles and business data stay in WorkDiary. Authenticated via a per-organization bearer token.',
    'base_url' => 'SCIM base URL',

    'new_token_heading' => 'New token',
    'new_token_hint' => 'Copy it now — the plaintext is shown only this once and is stored only as a hash afterwards.',

    'issue_heading' => 'Issue a token',
    'tokens_heading' => 'Issued tokens',
    'no_tokens' => 'No token issued yet.',

    'groups_heading' => 'SCIM groups → team',
    'groups_hint' => 'Groups provisioned by the identity provider. Mapping a group to a team mirrors its members into WorkDiary (team_user) — roles are never granted.',
    'no_groups' => 'No SCIM group provisioned yet.',

    'oidc_heading' => 'OIDC single sign-on',
    'oidc_hint' => 'Sign-in via OpenID Connect (Entra ID, Keycloak, Google …). Account linking uses issuer + subject only; SSO never creates accounts and never grants roles. After an IdP login, multi-factor checks are the identity provider’s responsibility.',
    'saml_heading' => 'SAML 2.0',
    'saml_hint' => 'SP-initiated sign-in via SAML 2.0. Assertions must be signed; IdP-initiated (unsolicited) responses are rejected. A second certificate can be stored in parallel for certificate rotation.',

    'break_glass_heading' => 'Break-glass accounts',
    'break_glass_hint' => 'Non-federated emergency accounts that may keep signing in with a password despite enforced SSO. Every use is audited. Keep at least one account, otherwise an IdP outage locks the organization out.',
    'no_break_glass' => 'No break-glass account defined.',

    'discover' => [
        'hint' => 'Enter your organization identifier to start signing in via your identity provider.',
        'org_label' => 'Organization identifier',
        'org_placeholder' => 'e.g. acme-inc',
        'submit' => 'Continue to identity provider',
        'back_to_login' => 'Back to sign-in',
    ],

    'protocol' => [
        'oidc' => 'OIDC',
        'saml' => 'SAML 2.0',
    ],

    'field' => [
        'label' => 'Label',
        'label_placeholder' => 'e.g. Entra ID production',
        'team_none' => '— no team —',
        'start_url' => 'SSO start URL',
        'callback_url' => 'Redirect/callback URL (register at the IdP)',
        'acs_url' => 'ACS URL (register at the IdP)',
        'metadata_url' => 'SP metadata URL',
        'issuer' => 'Issuer',
        'client_id' => 'Client ID',
        'client_secret' => 'Client secret',
        'secret_keep' => 'leave empty = unchanged',
        'scopes' => 'Scopes',
        'idp_entity_id' => 'IdP entity ID',
        'idp_sso_url' => 'IdP SSO URL',
        'idp_certificate' => 'IdP signing certificate (PEM)',
        'idp_certificate_next' => 'Successor certificate (rotation, optional)',
        'idp_certificate_next_hint' => 'During certificate rotation both are accepted.',
        'active' => 'Active',
        'enforced' => 'Enforce SSO',
        'enforced_hint' => 'Blocks password sign-in for all accounts of this organization (except break-glass).',
        'email_link' => 'First-login e-mail linking',
        'jit' => 'Create users on first login (JIT)',
        'jit_hint' => 'Creates a new account on the first IdP login (license limit applies). Never links existing accounts — e-mail collisions are rejected.',
        'jit_role' => 'JIT default role',
        'jit_role_none' => 'no role',
        'email_link_hint' => 'On the first SSO login, link an existing account via e-mail (only on exactly one match). Afterwards only issuer + subject counts.',
        'private_network' => 'Allow IdP on private network',
        'private_network_hint' => 'SSRF protection exception for on-premise IdPs (e.g. internal Keycloak).',
        'break_glass_user' => 'Account',
    ],

    'action' => [
        'issue' => 'Issue',
        'revoke' => 'Revoke',
        'save_mapping' => 'Save',
        'save_connection' => 'Save connection',
        'test_connection' => 'Test connection',
        'remove_connection' => 'Remove connection',
        'break_glass_add' => 'Set as break-glass account',
        'break_glass_remove' => 'Remove',
    ],

    'col' => [
        'status' => 'Status',
        'last_used' => 'Last used',
        'group' => 'Group',
        'members' => 'Members',
        'team' => 'Team',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'revoked' => 'Revoked',
        'enforced' => 'SSO enforced',
    ],

    'flash' => [
        'token_issued' => 'SCIM token issued.',
        'token_revoked' => 'SCIM token revoked.',
        'group_mapped' => 'Team mapping saved.',
        'connection_saved' => ':protocol connection saved.',
        'connection_ok' => ':protocol connection verified successfully.',
        'connection_removed' => 'Connection removed.',
        'break_glass_added' => 'Break-glass account set.',
        'break_glass_removed' => 'Break-glass status removed.',
    ],

    'error' => [
        'discovery_failed' => 'The identity provider’s OIDC discovery is unreachable or incomplete.',
        'issuer_mismatch' => 'The issuer in the discovery response does not match the configuration.',
        'token_exchange_failed' => 'The code exchange with the identity provider failed.',
        'token_invalid' => 'The identity provider’s sign-in token is invalid.',
        'token_expired' => 'The identity provider’s sign-in token has expired.',
        'jwks_failed' => 'The identity provider’s signing keys could not be loaded.',
        'no_account' => 'No WorkDiary account is linked to this identity. Please contact your administrator.',
        'org_without_sso' => 'No single sign-on is configured for this identifier.',
        'flow_expired' => 'The SSO sign-in has expired. Please try again.',
        'module_disabled' => 'Single sign-on is not available for this organization.',
        'url_not_public' => 'The URL is not publicly reachable. For internal identity providers enable “Allow IdP on private network”.',
        'entra_issuer_not_tenant_specific' => 'Microsoft Entra ID requires the tenant-specific issuer (https://login.microsoftonline.com/<tenant-guid>/v2.0) — never common/organizations.',
        'entra_email_link_forbidden' => 'First-login e-mail linking is blocked for Microsoft Entra ID: its email claim is unverified (nOAuth attack). Pre-provision identities (SCIM/manual) or use JIT.',
        'saml_invalid' => 'The SAML response is invalid.',
        'saml_unsolicited' => 'Unsolicited (IdP-initiated) SAML responses are rejected. Please start the sign-in from WorkDiary.',
        'saml_no_nameid' => 'The SAML response contains no NameID. Configure a NameID claim rule at the IdP (e.g. ADFS).',
        'saml_settings_invalid' => 'The SAML configuration is incomplete or invalid.',
        'saml_certificate_invalid' => 'The IdP certificate could not be read (PEM expected).',
    ],
];
