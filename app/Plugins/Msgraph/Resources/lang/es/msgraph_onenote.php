<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_onenote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// OneNote-Übernahme (Feature 155, MVP-815): Sektion im Msgraph-Admin-Panel + Flow-Flashes.
return [
    'heading' => 'Importar de OneNote',
    'intro' => 'Importa un bloc de notas de OneNote una vez o bajo demanda como notas o artículos de conocimiento, solo lectura (Notes.Read), sin escribir de vuelta ni sincronización continua. El bloc pasa a ser una colección y las secciones, subcolecciones.',
    'badge_connected' => 'Conectado',
    'badge_disabled' => 'Desactivado',
    'account' => 'Cuenta conectada',
    'connect' => 'Conectar OneNote',
    'disconnect' => 'Desconectar OneNote',
    'open_hub' => 'Ir a «Conocimiento»',
    'enable_hint' => 'Active primero «Permitir importación de OneNote» en los ajustes del plugin; solo entonces la conexión solicita el permiso adicional Notes.Read.',
    'flash' => [
        'not_configured' => 'Microsoft 365 no está configurado (faltan MSGRAPH_CLIENT_ID/SECRET).',
        'state_invalid' => 'El inicio de sesión ha caducado o no es válido; vuelva a empezar.',
        'oauth_denied' => 'Se canceló la autorización.',
        'oauth_failed' => 'La conexión falló (:class).',
        'connected' => 'OneNote conectado.',
        'disconnected' => 'OneNote desconectado; token de acceso eliminado.',
        'disabled' => 'La importación de OneNote está desactivada en los ajustes del plugin.',
    ],
];
