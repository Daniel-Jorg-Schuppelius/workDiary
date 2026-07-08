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
    'title' => 'SSO y servicios de directorio',
    'intro' => 'Aprovisionamiento SCIM 2.0: su proveedor de identidad (Entra ID, Keycloak, Okta …) crea, actualiza y desactiva cuentas. Una cuenta desactivada no puede volver a iniciar sesión de inmediato; los roles y los datos de negocio permanecen en WorkDiary. Autenticación mediante un token bearer por organización.',
    'base_url' => 'URL base de SCIM',

    'new_token_heading' => 'Nuevo token',
    'new_token_hint' => 'Cópielo ahora — el texto en claro se muestra solo esta vez y después se almacena únicamente como hash.',

    'issue_heading' => 'Emitir un token',
    'tokens_heading' => 'Tokens emitidos',
    'no_tokens' => 'Aún no se ha emitido ningún token.',

    'groups_heading' => 'Grupos SCIM → equipo',
    'groups_hint' => 'Grupos aprovisionados por el proveedor de identidad. Asignar un grupo a un equipo refleja sus miembros en WorkDiary (team_user); nunca se conceden roles.',
    'no_groups' => 'Aún no se ha aprovisionado ningún grupo SCIM.',

    'field' => [
        'label' => 'Etiqueta',
        'label_placeholder' => 'p. ej. Entra ID producción',
        'team_none' => '— sin equipo —',
    ],

    'action' => [
        'issue' => 'Emitir',
        'revoke' => 'Revocar',
        'save_mapping' => 'Guardar',
    ],

    'col' => [
        'status' => 'Estado',
        'last_used' => 'Último uso',
        'group' => 'Grupo',
        'members' => 'Miembros',
        'team' => 'Equipo',
    ],

    'status' => [
        'active' => 'Activo',
        'revoked' => 'Revocado',
    ],

    'flash' => [
        'token_issued' => 'Token SCIM emitido.',
        'token_revoked' => 'Token SCIM revocado.',
        'group_mapped' => 'Asignación de equipo guardada.',
    ],
];
