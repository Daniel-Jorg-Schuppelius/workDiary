<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Envío de correo vía Graph (Feature 102): sección de correo del panel de administración de Msgraph + mensajes del flujo.
return [
    'heading' => 'Envío de correo a través de Microsoft 365',
    'intro' => 'Envía los correos de WorkDiary (facturas, recordatorios de pago, notificaciones) a través de Microsoft Graph en lugar de SMTP — autenticación moderna, sin SMTP con autenticación básica.',
    'badge_connected' => 'Conectado',
    'badge_inactive' => 'Desconectado',
    'mailer_hint' => 'El mailer msgraph no está activo actualmente. Actívelo con MAIL_MAILER=msgraph (o una cadena failover que incluya msgraph) en la instalación.',
    'account' => 'Cuenta conectada',
    'from_address' => 'Dirección del remitente (opcional)',
    'from_placeholder' => 'p. ej. facturacion@empresa.com (buzón compartido)',
    'from_hint' => 'Vacío = la cuenta conectada envía como ella misma. Una dirección distinta requiere el permiso de Exchange «Enviar como» y el scope Mail.Send.Shared.',
    'save_to_sent' => 'Guardar una copia en la carpeta Elementos enviados',
    'connect' => 'Conectar el envío de correo',
    'disconnect' => 'Desconectar el envío de correo',
    'flash' => [
        'not_configured' => 'Microsoft 365 no está configurado (faltan MSGRAPH_CLIENT_ID/SECRET).',
        'state_invalid' => 'El proceso de inicio de sesión caducó o no es válido — inícielo de nuevo.',
        'oauth_denied' => 'La autorización fue cancelada.',
        'oauth_failed' => 'La conexión falló (:class).',
        'connected' => 'Envío de correo a través de Microsoft 365 conectado.',
        'disconnected' => 'Envío de correo desconectado — tokens de acceso eliminados.',
        'no_connection' => 'No se ha establecido ninguna conexión de correo con Microsoft 365.',
        'settings_saved' => 'Configuración de correo guardada.',
    ],
];
