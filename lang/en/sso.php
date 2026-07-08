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

    'field' => [
        'label' => 'Label',
        'label_placeholder' => 'e.g. Entra ID production',
        'team_none' => '— no team —',
    ],

    'action' => [
        'issue' => 'Issue',
        'revoke' => 'Revoke',
        'save_mapping' => 'Save',
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
        'revoked' => 'Revoked',
    ],

    'flash' => [
        'token_issued' => 'SCIM token issued.',
        'token_revoked' => 'SCIM token revoked.',
        'group_mapped' => 'Team mapping saved.',
    ],
];
