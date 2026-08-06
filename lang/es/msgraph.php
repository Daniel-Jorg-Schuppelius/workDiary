<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Calendario de Microsoft 365',
    'intro' => 'Las citas de WorkDiary se publican mediante Microsoft Graph en un calendario de la cuenta de Microsoft 365 conectada. WorkDiary sigue siendo la fuente autoritativa; las citas canceladas desaparecen allí y las ejecuciones repetidas nunca crean duplicados. Las citas externas nunca se leen.',
    'plugin_description' => 'Publica citas de forma idempotente en un calendario de Microsoft 365 (Microsoft Graph, OAuth2): solo publicación, calendario de destino seleccionable.',
    'not_configured_hint' => 'MSGRAPH_CLIENT_ID/SECRET (y MSGRAPH_TENANT si procede) no están configurados: la conexión requiere primero un registro de aplicación en el tenant de Microsoft.',

    // Presencia de Teams en la página de asistencia (Feature 102, F).
    'presence' => [
        'heading' => 'Equipo (estado de Teams)',
        'state' => [
            'Available' => 'Disponible',
            'AvailableIdle' => 'Disponible (inactivo)',
            'Busy' => 'Ocupado',
            'BusyIdle' => 'Ocupado (inactivo)',
            'DoNotDisturb' => 'No molestar',
            'Away' => 'Ausente',
            'BeRightBack' => 'Vuelvo enseguida',
            'Offline' => 'Sin conexión',
            'PresenceUnknown' => 'Desconocido',
        ],
    ],
    // Free/busy en el diálogo de eventos (Feature 102, C2).
    'availability' => [
        'check' => 'Comprobar disponibilidad (Microsoft 365)',
        'hint' => 'Libre/ocupado de los participantes seleccionados en la franja horaria — sin detalles de citas.',
        'missing_input' => 'Elija inicio, fin y al menos un participante.',
        'no_connection' => 'No hay conexión de calendario de Microsoft 365 activa.',
        'failed' => 'La consulta de disponibilidad falló.',
        'free' => 'libre',
        'busy' => 'ocupado',
        'unknown' => 'desconocido',
    ],
    // Registro de aplicación por organización (Feature 102 variante B).
    'settings' => [
        'client_id' => 'ID de cliente (registro de aplicación propio)',
        'client_id_help' => 'Vacío = la aplicación de la instalación. Una aplicación Entra propia debe registrar las mismas URIs de redirección.',
        'client_secret' => 'Secreto de cliente',
        'client_secret_help' => 'Se guarda cifrado; dejar vacío para conservar el valor almacenado.',
        'tenant' => 'Tenant (ID de directorio)',
        'tenant_help' => 'GUID del tenant de Entra; vacío = valor de la aplicación de la instancia (por defecto «common»).',
        'tenant_invalid' => 'El tenant debe ser un GUID de directorio (o common/organizations/consumers).',
    ],
    'health' => [
        'badge_ok' => 'Conectado',
        'badge_failing' => 'Inaccesible',
        'badge_inactive' => 'Inactivo',
        'not_configured' => 'Microsoft 365 no está configurado (faltan MSGRAPH_CLIENT_ID/SECRET).',
        'no_org_context' => 'Configurado (sin organización en el contexto).',
        'no_connection' => 'No se ha establecido ninguna conexión con Microsoft 365.',
        'inactive' => 'La conexión con Microsoft 365 está desconectada o desactivada.',
        'side_connections' => 'Las conexiones secundarias de Microsoft 365 requieren atención (:intake recepción de documentos, :backup copia de seguridad, :mail correo — vuelva a autenticarse o revise los permisos).',
        'ok' => 'Conectado: lista de calendarios disponible.',
        'failing' => 'Microsoft Graph inaccesible o acceso denegado.',
        'error' => 'Error de Microsoft Graph (:class).',
    ],

    'action' => [
        'connect' => 'Conectar con Microsoft 365',
        'publish' => 'Publicar ahora',
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
    ],

    'calendar' => [
        'heading' => 'Calendario de destino',
        'help' => 'En qué calendario de la cuenta conectada se publica. Sin selección se usa el calendario predeterminado.',
        'target' => 'Calendario',
        'default' => 'Calendario predeterminado',
        'teams_meetings' => 'Crear los eventos nuevos como reuniones de Teams (enlace de acceso)',
        'teams_meetings_hint' => 'Solo afecta a los eventos publicados nuevos — Graph no puede volver a poner «offline» un evento existente.',
        'two_way' => 'Bidireccional: importar cambios externos como propuestas',
        'two_way_hint' => 'Importación delta del calendario de destino — los eventos externos nuevos, ediciones externas y borrados se convierten en casos de la bandeja de integraciones (nunca creación a ciegas).',
    ],

    'flash' => [
        'not_configured' => 'Microsoft 365 no está configurado (faltan MSGRAPH_CLIENT_ID/SECRET).',
        'state_invalid' => 'El flujo OAuth ha caducado o no es válido. Inténtelo de nuevo.',
        'oauth_denied' => 'La conexión fue rechazada o cancelada.',
        'oauth_failed' => 'El intercambio de tokens ha fallado (:class).',
        'connected' => 'Cuenta de Microsoft 365 conectada.',
        'disconnected' => 'Conexión con Microsoft 365 desconectada. Las citas ya publicadas se conservan en el sistema externo.',
        'no_connection' => 'No hay ninguna conexión activa con Microsoft 365.',
        'calendar_saved' => 'Calendario de destino guardado.',
        'calendar_invalid' => 'El calendario seleccionado no se ha encontrado.',
        'publish_done' => 'Publicación iniciada.',
    ],
];
