<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sharepoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Almacenamiento SharePoint',
    'intro' => 'Los documentos aprobados se replican por tipo de documento en una biblioteca de documentos de SharePoint mediante Microsoft Graph — con comprobante de transferencia (hash, hora, destino). WorkDiary sigue siendo la referencia; los cambios externos en archivos replicados aparecen como conflictos, nunca se adoptan en silencio.',
    'plugin_description' => 'Replica los documentos aprobados en una biblioteca de documentos de SharePoint mediante Microsoft Graph — con comprobante de transferencia y visualización de conflictos, sin canal de retorno.',
    'not_configured_hint' => 'SHAREPOINT_CLIENT_ID/SECRET (o los valores de reserva MSGRAPH_*) no están definidos — la conexión solo puede establecerse tras el registro de la aplicación en el tenant de Microsoft.',

    'health' => [
        'badge_ok' => 'Conectado',
        'badge_failing' => 'No accesible',
        'badge_inactive' => 'Inactivo',
        'not_configured' => 'SharePoint no está configurado (faltan SHAREPOINT_/MSGRAPH_CLIENT_ID/SECRET).',
        'no_org_context' => 'Configurado (sin organización en el contexto).',
        'no_connection' => 'No se ha establecido ninguna conexión con SharePoint.',
        'inactive' => 'La conexión con SharePoint está desconectada, en pausa o sin biblioteca de destino.',
        'ok' => 'Conectado — biblioteca de destino accesible.',
        'failing' => 'Microsoft Graph no accesible o acceso denegado.',
        'error' => 'Error de Microsoft Graph (:class).',
    ],

    'action' => [
        'connect' => 'Conectar con Microsoft 365',
        'mirror' => 'Replicar ahora',
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
    ],

    'target' => [
        'heading' => 'Destino: sitio + biblioteca de documentos',
        'help' => 'Primero busque un sitio y luego elija la biblioteca de documentos. Ambos se validan en el servidor mediante Microsoft Graph — con Sites.Selected solo aparecen los sitios autorizados.',
        'current' => 'Destino actual',
        'search' => 'Buscar sitio',
        'search_placeholder' => 'Nombre del sitio o palabra clave',
        'search_action' => 'Buscar',
        'no_sites' => 'No se encontraron sitios (revise el término de búsqueda; con Sites.Selected el administrador del tenant debe autorizar el sitio).',
        'selected' => 'Seleccionado',
        'drive' => 'Biblioteca de documentos',
        'no_drives' => 'No se encontraron bibliotecas de documentos en este sitio.',
    ],

    'settings' => [
        'heading' => 'Reglas de carpetas + orígenes',
    ],

    'field' => [
        'default_folder' => 'Carpeta predeterminada',
        'active' => 'Activo',
        'sources' => 'Contenidos replicados',
        'source_document' => 'Documentos (DMS)',
        'source_invoice_pdf' => 'Facturas (PDF)',
        'source_protocol_pdf' => 'Protocolos (PDF)',
        'sources_help' => 'Qué contenidos se replican en esta biblioteca. Sin selección, solo los documentos aprobados.',
    ],

    'folder' => [
        'heading' => 'Tipo de documento → carpeta',
        'help' => 'Asigna tipos de documento a una subcarpeta (relativa a la biblioteca). Sin coincidencia se aplica la carpeta predeterminada.',
        'type_placeholder' => '— tipo de documento —',
        'path_placeholder' => 'Subcarpeta',
    ],

    'conflict' => [
        'subtitle' => 'Cambio externo detectado — replicación pausada (sin sobrescritura).',
        'action' => [
            'overwrite' => 'Sobrescribir remoto',
            'import' => 'Importar como nueva versión',
            'detach' => 'Desvincular la replicación',
        ],
        'confirm' => [
            'overwrite' => '¿Sobrescribir el archivo externo con el estado local? El cambio externo se perderá.',
            'import' => '¿Adoptar el estado externo como nueva versión local?',
            'detach' => '¿Desvincular definitivamente la replicación de este documento? La conexión sigue activa.',
        ],
        'flash' => [
            'overwritten' => 'Archivo externo sobrescrito con el estado local.',
            'imported' => 'Estado externo importado como nueva versión local.',
            'detached' => 'Replicación de este documento desvinculada.',
            'failed' => 'La resolución del conflicto falló: :reason',
        ],
        'import_note' => 'Importado desde SharePoint (resolución de conflicto).',
    ],

    'flash' => [
        'not_configured' => 'SharePoint no está configurado (faltan ID de cliente/secreto).',
        'state_invalid' => 'El flujo OAuth expiró o no es válido — vuelva a conectarse.',
        'oauth_denied' => 'Microsoft no devolvió un código de autorización (¿flujo cancelado?).',
        'oauth_failed' => 'Intercambio de token fallido (:class).',
        'connected' => 'Conectado con Microsoft 365. Ahora elija sitio + biblioteca.',
        'disconnected' => 'Conexión con SharePoint desconectada. Los archivos ya replicados permanecen en el destino externo.',
        'no_connection' => 'No hay ninguna conexión activa con SharePoint.',
        'site_invalid' => 'El sitio elegido no es accesible o no está autorizado.',
        'drive_invalid' => 'La biblioteca de documentos elegida no pertenece al sitio elegido.',
        'target_saved' => 'Biblioteca de destino guardada.',
        'saved' => 'Configuración de SharePoint guardada.',
        'mirror_done' => 'Replicación iniciada.',
    ],
];
