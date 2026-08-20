<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Cloud-Dokumenteingang (Feature 080).
return [
    'validation' => [
        'pattern_empty' => 'El patrón de ruta no debe estar vacío.',
        'pattern_triple_star' => 'Patrón no válido: «***» no está permitido (solo * y **).',
        'unknown_variable' => 'Variable de ruta desconocida :variable.',
        'duplicate_variable' => 'La variable de ruta :variable aparece varias veces.',
    ],
    'title' => [
        'index' => 'Entrada de documentos en la nube',
        'subtitle' => 'Leer documentos de carpetas en la nube supervisadas y enrutarlos a facturas entrantes y DMS mediante reglas de carpetas.',
        'empty' => 'Aún no hay conexiones en la nube.',
    ],
    'field' => [
        'provider' => 'Proveedor',
        'name' => 'Nombre',
        'account' => 'Cuenta',
        'root_folder' => 'Carpeta raíz',
        'routes' => 'Reglas',
        'status' => 'Estado',
        'account_unconfirmed' => 'Cuenta aún sin confirmar',
        'container' => 'Contenedor/unidad',
        'root_folder_id' => 'ID de carpeta raíz (opcional)',
    ],
    'picker' => [
        'search_label' => 'Buscar contenedores',
        'search_placeholder' => 'vacío = unidades propias; un término de búsqueda también encuentra bibliotecas de SharePoint',
        'load' => 'Cargar contenedores',
        'load_failed' => 'No se pudieron cargar los contenedores — introduzca el ID manualmente.',
    ],
    'action' => [
        'connect_dropbox' => 'Conectar Dropbox',
        'connect_microsoft' => 'Conectar Microsoft 365',
        'connect_google' => 'Conectar Google Drive',
        'connect_nextcloud' => 'Conectar Nextcloud',
        'preview' => 'Vista previa',
        'save_folder' => 'Aplicar carpeta',
        'disconnect' => 'Desconectar',
        'disconnect_confirm' => '¿Desconectar realmente? Los documentos importados y los comprobantes permanecen; solo se eliminan el acceso y el punto de control.',
    ],
    'flash' => [
        'not_configured' => 'El proveedor no está configurado (faltan las claves de la aplicación).',
        'state_invalid' => 'El proceso de inicio de sesión caducó o no es válido — inténtelo de nuevo.',
        'oauth_denied' => 'La autorización fue cancelada.',
        'oauth_failed' => 'El inicio de sesión falló (:class).',
        'account_failed' => 'No se pudo confirmar la cuenta (:class).',
        'connected' => 'Conexión establecida — cuenta confirmada.',
        'folder_selected' => 'Carpeta raíz aplicada — la próxima ejecución comienza con una sincronización nueva.',
        'overlapping_root' => 'La carpeta raíz se solapa con la conexión «:name» de la misma cuenta.',
        'preview_failed' => 'La vista previa falló (:class).',
        'preview_result' => 'Vista previa (primera página:more): :files archivos, :size — :matched con regla, :unmatched sin asignar.',
        'disconnected' => 'Conexión eliminada — comprobantes y documentos importados permanecen.',
        'route_saved' => 'Regla de carpeta guardada.',
        'route_deleted' => 'Regla de carpeta eliminada.',
    ],
    'dropbox' => [
        'description' => 'Lee documentos de carpetas de Dropbox supervisadas (entrada de documentos en la nube) — con reglas de carpetas, comprobante de transferencia y bandeja para casos dudosos.',
        'health' => [
            'not_configured' => 'Claves de la aplicación de Dropbox sin configurar.',
            'no_org_context' => 'Sin contexto de organización (ejecución del sistema).',
            'attention' => 'Al menos una conexión de Dropbox necesita atención (reautenticación/bloqueada).',
            'backup_attention' => 'El destino de copia de seguridad de Dropbox necesita atención (reautenticación/bloqueado) — afecta a todas las organizaciones.',
            'ok' => 'Conexiones de Dropbox correctas.',
            'error' => 'La comprobación de estado falló (:class).',
        ],
    ],
    'google' => [
        'description' => 'Lee documentos de carpetas de Google Drive supervisadas (entrada de documentos en la nube) — Mi unidad y unidades compartidas; despliegue bloqueado hasta la verificación OAuth de Google.',
        'health' => [
            'not_configured' => 'Claves de cliente de Google Drive sin configurar.',
            'no_org_context' => 'Sin contexto de organización (ejecución del sistema).',
            'attention' => 'Al menos una conexión de Google Drive necesita atención (reautenticación/bloqueada).',
            'backup_attention' => 'El destino de copia de seguridad de Google Drive necesita atención (reautenticación/bloqueado) — afecta a todas las organizaciones.',
            'ok' => 'Conexiones de Google Drive correctas.',
            'error' => 'La comprobación de estado falló (:class).',
        ],
    ],
    'nextcloud' => [
        'description' => 'Incorpora documentos de carpetas de Nextcloud supervisadas (WebDAV) — con reglas de carpeta, comprobante de entrega y bandeja de entrada para casos ambiguos.',
        'health' => [
            'no_org_context' => 'Sin contexto de organización (ejecución del sistema).',
            'attention' => 'Al menos una conexión de Nextcloud requiere atención (reautenticación/bloqueada).',
            'backup_attention' => 'El destino de copia de seguridad de Nextcloud necesita atención (reautenticación/bloqueado) — afecta a todas las organizaciones.',
            'ok' => 'Conexiones de Nextcloud en orden.',
            'error' => 'La comprobación de estado falló (:class).',
        ],
        'connect_title' => 'Conectar Nextcloud',
        'connect_legend' => 'Credenciales',
        'connect_submit' => 'Conectar',
        'field' => [
            'server_url' => 'URL del servidor',
            'server_url_help' => 'Solo HTTPS. Ejemplo: https://cloud.example.com',
            'username' => 'Nombre de usuario',
            'app_password' => 'Contraseña de aplicación',
            'app_password_help' => 'Una contraseña de aplicación revocable (Ajustes › Seguridad), nunca la contraseña normal de la cuenta.',
        ],
        'validation' => [
            'https_required' => 'La URL del servidor debe comenzar con https://.',
            'unsafe_url' => 'La URL del servidor debe ser accesible públicamente (sin destino interno/privado).',
        ],
    ],
    'route' => [
        'heading' => 'Reglas de carpetas',
        'create' => 'Crear regla',
        'edit' => 'Editar regla',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'delete_confirm' => '¿Eliminar realmente esta regla?',
        'basics' => 'Regla',
        'pattern' => 'Patrón de ruta',
        'pattern_help' => '* = un segmento, ** = cualquier profundidad; variables: {customer_number}, {project_number}, {order_number}, {asset_number}, {contract_number}. Los casos dudosos van a la bandeja de integración.',
        'target' => 'Destino',
        'document_type' => 'Tipo de documento',
        'priority' => 'Prioridad',
        'extensions' => 'Extensiones permitidas',
        'extensions_help' => 'Separadas por comas; vacío = todas (salvo bloqueos globales).',
        'max_size' => 'Tamaño máx. (bytes)',
        'auto_version' => 'Adoptar automáticamente nuevas revisiones como versiones',
        'auto_version_help' => 'Sin aprobación, las nuevas revisiones se convierten en propuestas de versión en la bandeja.',
        'active' => 'Activa',
        'inactive' => 'Inactiva',
        'empty' => 'Aún no hay reglas — sin una regla válida la conexión no importa.',
    ],
    'log' => [
        'heading' => 'Registro de importación',
        'empty' => 'Sin transferencias todavía.',
        'path' => 'Ruta de origen',
        'revision' => 'Revisión',
        'reason' => 'Motivo',
        'when' => 'Fecha',
    ],
    // Informe de importación (función 080 P9; auditoría 2026-08, W4.4).
    'report' => [
        'title' => 'Informe de entrada de documentos en la nube',
        'nav' => 'Entrada de documentos en la nube',
        'subtitle' => 'Documentos importados y rechazados en el periodo',
        'kpi' => [
            'total' => 'Operaciones totales',
            'imported' => 'Importados',
            'inbox' => 'En la bandeja de asignación',
            'rejected' => 'Rechazados',
        ],
        'chart' => [
            'per_period' => 'Operaciones :per',
            'by_provider' => 'Operaciones por proveedor',
        ],
        'unit' => ['documents' => 'Documentos'],
        'section' => [
            'connections' => 'Conexiones',
            'reasons' => 'Motivos de rechazo',
            'items' => 'Operaciones',
        ],
        'column' => [
            'folder' => 'Carpeta',
            'provider' => 'Proveedor',
            'status' => 'Estado',
            'imported' => 'Importados',
            'rejected' => 'Rechazados',
            'last_run' => 'Última ejecución',
            'reason' => 'Motivo',
            'count' => 'Cantidad',
            'date' => 'Fecha y hora',
            'path' => 'Ruta de origen',
        ],
        'empty' => [
            'connections' => 'Aún no hay ninguna conexión en la nube vinculada.',
            'reasons' => 'Sin rechazos en el periodo seleccionado.',
            'items' => 'Sin datos en el periodo seleccionado.',
        ],
    ],
];
