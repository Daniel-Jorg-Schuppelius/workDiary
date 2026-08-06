<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_contacts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Envío de contactos (Feature 102, corte D): sección en el panel de Msgraph + botón en la ficha del cliente.
return [
    'heading' => 'Enviar contactos a Outlook',
    'intro' => 'Envía clientes de WorkDiary como contactos de Outlook de la cuenta conectada bajo demanda (idempotente — sin duplicados al repetir).',
    'badge_connected' => 'Conectado',
    'badge_inactive' => 'Desconectado',
    'account' => 'Cuenta conectada',
    'connect' => 'Conectar el envío de contactos',
    'disconnect' => 'Desconectar el envío de contactos',
    'push_button' => 'A Outlook',
    'flash' => [
        'not_configured' => 'Microsoft 365 no está configurado (faltan MSGRAPH_CLIENT_ID/SECRET).',
        'state_invalid' => 'El proceso de inicio de sesión caducó o no es válido — inícielo de nuevo.',
        'oauth_denied' => 'La autorización fue cancelada.',
        'oauth_failed' => 'La conexión falló (:class).',
        'connected' => 'Envío de contactos a Outlook conectado.',
        'disconnected' => 'Envío de contactos desconectado — tokens de acceso eliminados.',
        'no_connection' => 'No se ha establecido ninguna conexión de contactos con Microsoft 365.',
        'plugin_disabled' => 'El plugin de Microsoft 365 no está activado.',
        'pushed' => 'Cliente enviado como contacto de Outlook (ID :id).',
        'push_failed' => 'Envío fallido (:class).',
    ],
];
