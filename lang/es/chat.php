<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : chat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Mensajería de equipo',
    'intro' => 'Microsoft Teams y Mattermost/Rocket.Chat reciben los mismos eventos que los demás canales de notificación. Qué eventos se envían a los canales se controla en la matriz de notificaciones (casilla « Teams »/« Mattermost » por evento). La URL del canal se almacena cifrada.',
    'to_matrix' => 'Ir a la matriz de notificaciones',
    'open' => 'Abrir',

    'channels_heading' => 'Canales',
    'no_channels' => 'Aún no hay ningún canal conectado.',
    'add_heading' => 'Añadir canal',

    'kind' => [
        'teams' => 'Microsoft Teams',
        'mattermost' => 'Mattermost / Rocket.Chat',
    ],

    'field' => [
        'name' => 'Etiqueta',
        'kind' => 'Tipo de canal',
        'webhook_url' => 'URL de webhook',
        'webhook_url_help' => 'URL del webhook entrante de Teams (conector/flujo) o de Mattermost/Rocket.Chat. Contiene el secreto — se almacena cifrada.',
    ],

    'action' => [
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
        'test' => 'Probar',
    ],

    'col' => [
        'status' => 'Estado',
    ],

    'status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'auto_disabled' => 'Desactivado automáticamente',
    ],

    'flash' => [
        'saved' => 'Canal guardado.',
        'disconnected' => 'Canal desconectado.',
        'invalid_url' => 'La URL del webhook debe empezar por https://.',
        'test_sent' => 'Mensaje de prueba enviado.',
        'test_failed' => 'Error en el mensaje de prueba: canal inaccesible.',
        'test_inactive' => 'El canal está desactivado.',
    ],
    'test' => [
        'event' => 'Prueba',
        'title' => 'Mensaje de prueba de WorkDiary',
        'message' => 'Este canal está conectado correctamente. ✅',
    ],
];
