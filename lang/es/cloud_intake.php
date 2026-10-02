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
