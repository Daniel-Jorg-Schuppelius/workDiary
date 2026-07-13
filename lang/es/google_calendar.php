<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : google_calendar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Google Calendar',
    'intro' => 'Las citas de WorkDiary se publican mediante la API de Google Calendar en un calendario de la cuenta de Google conectada. WorkDiary sigue siendo la fuente autoritativa; las citas canceladas desaparecen allí y las ejecuciones repetidas nunca crean duplicados. Las citas externas nunca se leen.',
    'plugin_description' => 'Publica citas de forma idempotente en un calendario de Google (Calendar API v3, OAuth2): solo publicación, calendario de destino seleccionable.',
    'not_configured_hint' => 'GOOGLE_CALENDAR_CLIENT_ID/SECRET no están configurados: la conexión requiere primero un cliente OAuth en la Google Cloud Console (los scopes de calendario son «sensitive»: verificación de marca o tipo de consentimiento «Internal» para Workspace).',

    'health' => [
        'badge_ok' => 'Conectado',
        'badge_failing' => 'Inaccesible',
        'badge_inactive' => 'Inactivo',
        'not_configured' => 'Google Calendar no está configurado (faltan GOOGLE_CALENDAR_CLIENT_ID/SECRET).',
        'no_org_context' => 'Configurado (sin organización en el contexto).',
        'no_connection' => 'No se ha establecido ninguna conexión con Google Calendar.',
        'inactive' => 'La conexión con Google Calendar está desconectada o desactivada.',
        'ok' => 'Conectado: lista de calendarios disponible.',
        'failing' => 'API de Google Calendar inaccesible o acceso denegado.',
        'error' => 'Error de Google Calendar (:class).',
    ],

    'action' => [
        'connect' => 'Conectar con Google',
        'publish' => 'Publicar ahora',
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
    ],

    'calendar' => [
        'heading' => 'Calendario de destino',
        'help' => 'En qué calendario de la cuenta conectada se publica. Sin selección se usa el calendario principal.',
        'target' => 'Calendario',
        'default' => 'Calendario principal',
    ],

    'flash' => [
        'not_configured' => 'Google Calendar no está configurado (faltan GOOGLE_CALENDAR_CLIENT_ID/SECRET).',
        'state_invalid' => 'El flujo OAuth ha caducado o no es válido. Inténtelo de nuevo.',
        'oauth_denied' => 'La conexión fue rechazada o cancelada.',
        'oauth_failed' => 'El intercambio de tokens ha fallado (:class).',
        'connected' => 'Cuenta de Google conectada.',
        'disconnected' => 'Conexión con Google Calendar desconectada. Las citas ya publicadas se conservan en el sistema externo.',
        'no_connection' => 'No hay ninguna conexión activa con Google Calendar.',
        'calendar_saved' => 'Calendario de destino guardado.',
        'calendar_invalid' => 'El calendario seleccionado no se ha encontrado.',
        'publish_done' => 'Publicación iniciada.',
    ],
];
