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
    'title' => 'SSO e servizi di directory',
    'intro' => 'Provisioning SCIM 2.0: il vostro provider di identità (Entra ID, Keycloak, Okta …) crea, aggiorna e disattiva gli account. Un account disattivato non può più accedere immediatamente; ruoli e dati aziendali restano in WorkDiary. Autenticazione tramite un token bearer per organizzazione.',
    'base_url' => 'URL base SCIM',

    'new_token_heading' => 'Nuovo token',
    'new_token_hint' => 'Copialo ora — il testo in chiaro viene mostrato solo questa volta e successivamente memorizzato solo come hash.',

    'issue_heading' => 'Emetti un token',
    'tokens_heading' => 'Token emessi',
    'no_tokens' => 'Nessun token emesso finora.',

    'groups_heading' => 'Gruppi SCIM → team',
    'groups_hint' => 'Gruppi provisionati dal provider di identità. Associare un gruppo a un team rispecchia i suoi membri in WorkDiary (team_user); i ruoli non vengono mai assegnati.',
    'no_groups' => 'Nessun gruppo SCIM ancora provisionato.',

    'field' => [
        'label' => 'Etichetta',
        'label_placeholder' => 'ad es. Entra ID produzione',
        'team_none' => '— nessun team —',
    ],

    'action' => [
        'issue' => 'Emetti',
        'revoke' => 'Revoca',
        'save_mapping' => 'Salva',
    ],

    'col' => [
        'status' => 'Stato',
        'last_used' => 'Ultimo utilizzo',
        'group' => 'Gruppo',
        'members' => 'Membri',
        'team' => 'Team',
    ],

    'status' => [
        'active' => 'Attivo',
        'revoked' => 'Revocato',
    ],

    'flash' => [
        'token_issued' => 'Token SCIM emesso.',
        'token_revoked' => 'Token SCIM revocato.',
        'group_mapped' => 'Associazione team salvata.',
    ],
];
