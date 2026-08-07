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

    'oidc_heading' => 'Inicio de sesión único OIDC',
    'oidc_hint' => 'Inicio de sesión mediante OpenID Connect (Entra ID, Keycloak, Google …). La vinculación de cuentas usa solo issuer + subject; el SSO nunca crea cuentas ni concede roles. Tras un inicio de sesión en el IdP, la verificación multifactor es responsabilidad del proveedor de identidad.',
    'saml_heading' => 'SAML 2.0',
    'saml_hint' => 'Inicio de sesión iniciado por el SP mediante SAML 2.0. Las aserciones deben estar firmadas; las respuestas iniciadas por el IdP (no solicitadas) se rechazan. Para la rotación de certificados puede almacenarse un segundo certificado en paralelo.',

    'break_glass_heading' => 'Cuentas de emergencia (break-glass)',
    'break_glass_hint' => 'Cuentas de emergencia no federadas que pueden seguir iniciando sesión con contraseña pese al SSO obligatorio. Cada uso queda auditado. Mantenga al menos una cuenta; de lo contrario, una caída del IdP bloquea a la organización.',
    'no_break_glass' => 'No hay ninguna cuenta de emergencia definida.',
    'domains_heading' => 'Dominios de correo',
    'domains_hint' => 'WorkDiary deduce la organización a partir del dominio de correo del inicio de sesión. Los dominios son únicos a nivel global.',
    'no_domains' => 'Aún no se ha asignado ningún dominio de correo.',

    'provider' => [
        'custom' => 'Proveedor OIDC personalizado',
        'microsoft' => 'Microsoft 365',
        'google' => 'Google Workspace',
    ],

    'choose' => [
        'hint' => 'Hay varios proveedores de inicio de sesión configurados para :org. Elija uno.',
    ],

    'discover' => [
        'hint' => 'Introduzca el identificador de su organización para iniciar sesión a través de su proveedor de identidad.',
        'org_label' => 'Identificador de la organización',
        'org_placeholder' => 'p. ej. acme-sl',
        'email_label' => 'Correo electrónico',
        'email_placeholder' => 'p. ej. nombre@empresa.es',
        'submit' => 'Continuar al proveedor de identidad',
        'back_to_login' => 'Volver al inicio de sesión',
    ],

    'protocol' => [
        'oidc' => 'OIDC',
        'saml' => 'SAML 2.0',
    ],

    'field' => [
        'label' => 'Etiqueta',
        'label_placeholder' => 'p. ej. Entra ID producción',
        'tenant' => 'Directorio (inquilino)',
        'tenant_placeholder' => 'GUID del inquilino o dominio verificado',
        'tenant_hint' => 'Específico del inquilino — nunca common/organizations.',
        'tenant_keep' => 'dejar vacío = sin cambios',
        'domain' => 'Dominio de correo',
        'domain_placeholder' => 'p. ej. empresa.es',
        'team_none' => '— sin equipo —',
        'start_url' => 'URL de inicio SSO',
        'callback_url' => 'URL de redirección/callback (registrar en el IdP)',
        'acs_url' => 'URL ACS (registrar en el IdP)',
        'metadata_url' => 'URL de metadatos del SP',
        'issuer' => 'Issuer',
        'client_id' => 'ID de cliente',
        'client_secret' => 'Secreto de cliente',
        'secret_keep' => 'dejar vacío = sin cambios',
        'scopes' => 'Scopes',
        'idp_entity_id' => 'Entity ID del IdP',
        'idp_sso_url' => 'URL SSO del IdP',
        'idp_certificate' => 'Certificado de firma del IdP (PEM)',
        'idp_certificate_next' => 'Certificado sucesor (rotación, opcional)',
        'idp_certificate_next_hint' => 'Durante la rotación de certificados se aceptan ambos.',
        'active' => 'Activo',
        'enforced' => 'SSO obligatorio',
        'enforced_hint' => 'Bloquea el inicio de sesión con contraseña para todas las cuentas de esta organización (excepto break-glass).',
        'email_link' => 'Vinculación inicial por correo',
        'jit' => 'Crear usuarios en el primer inicio de sesión (JIT)',
        'jit_hint' => 'Crea una cuenta nueva en el primer inicio de sesión del IdP (se aplica el límite de licencia). Nunca vincula cuentas existentes — las colisiones de correo se rechazan.',
        'jit_role' => 'Rol predeterminado JIT',
        'jit_role_none' => 'sin rol',
        'email_link_hint' => 'En el primer inicio de sesión SSO, vincular una cuenta existente por correo (solo con exactamente una coincidencia). Después solo cuentan issuer + subject.',
        'private_network' => 'Permitir IdP en red privada',
        'private_network_hint' => 'Excepción a la protección SSRF para IdP locales (p. ej. Keycloak interno).',
        'break_glass_user' => 'Cuenta',
    ],

    'action' => [
        'issue' => 'Emitir',
        'revoke' => 'Revocar',
        'save_mapping' => 'Guardar',
        'save_connection' => 'Guardar conexión',
        'test_connection' => 'Probar conexión',
        'remove_connection' => 'Eliminar conexión',
        'break_glass_add' => 'Definir como cuenta de emergencia',
        'break_glass_remove' => 'Quitar',
        'domain_add' => 'Añadir dominio',
        'domain_remove' => 'Eliminar',
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
        'inactive' => 'Inactivo',
        'revoked' => 'Revocado',
        'enforced' => 'SSO obligatorio',
    ],

    'flash' => [
        'token_issued' => 'Token SCIM emitido.',
        'token_revoked' => 'Token SCIM revocado.',
        'group_mapped' => 'Asignación de equipo guardada.',
        'connection_saved' => 'Conexión :protocol guardada.',
        'connection_ok' => 'Conexión :protocol verificada correctamente.',
        'connection_removed' => 'Conexión eliminada.',
        'break_glass_added' => 'Cuenta de emergencia definida.',
        'break_glass_removed' => 'Estado de emergencia retirado.',
        'domain_added' => 'Dominio de correo añadido.',
        'domain_removed' => 'Dominio de correo eliminado.',
    ],

    'error' => [
        'discovery_failed' => 'El discovery OIDC del proveedor de identidad no está disponible o está incompleto.',
        'issuer_mismatch' => 'El issuer de la respuesta de discovery no coincide con la configuración.',
        'token_exchange_failed' => 'El intercambio de código con el proveedor de identidad ha fallado.',
        'token_invalid' => 'El token de inicio de sesión del proveedor de identidad no es válido.',
        'token_expired' => 'El token de inicio de sesión del proveedor de identidad ha caducado.',
        'jwks_failed' => 'No se pudieron cargar las claves de firma del proveedor de identidad.',
        'no_account' => 'Ninguna cuenta de WorkDiary está vinculada a esta identidad. Póngase en contacto con su administración.',
        'org_without_sso' => 'No hay inicio de sesión único configurado para este identificador.',
        'email_without_sso' => 'No hay inicio de sesión único configurado para este dominio de correo.',
        'tenant_required' => 'Microsoft 365 requiere el directorio (inquilino).',
        'google_issuer_invalid' => 'Para Google Workspace solo se permite el emisor oficial https://accounts.google.com.',
        'domain_invalid' => 'Introduzca un dominio de correo válido.',
        'domain_taken' => 'Este dominio de correo ya está asignado a otra organización.',
        'flow_expired' => 'El inicio de sesión SSO ha caducado. Inténtelo de nuevo.',
        'module_disabled' => 'El inicio de sesión único no está disponible para esta organización.',
        'url_not_public' => 'La URL no es accesible públicamente. Para proveedores internos active «Permitir IdP en red privada».',
        'entra_issuer_not_tenant_specific' => 'Microsoft Entra ID requiere el issuer específico del tenant (https://login.microsoftonline.com/<GUID-del-tenant>/v2.0) — nunca common/organizations.',
        'entra_email_link_forbidden' => 'La vinculación inicial por correo está bloqueada para Microsoft Entra ID: su claim de correo no está verificado (ataque nOAuth). Aprovisione las identidades previamente (SCIM/manual) o use JIT.',
        'saml_invalid' => 'La respuesta SAML no es válida.',
        'saml_unsolicited' => 'Las respuestas SAML no solicitadas (iniciadas por el IdP) se rechazan. Inicie la sesión desde WorkDiary.',
        'saml_no_nameid' => 'La respuesta SAML no contiene NameID. Configure una regla de claim NameID en el IdP (p. ej. ADFS).',
        'saml_settings_invalid' => 'La configuración SAML está incompleta o no es válida.',
        'saml_certificate_invalid' => 'No se pudo leer el certificado del IdP (se espera PEM).',
    ],
];
