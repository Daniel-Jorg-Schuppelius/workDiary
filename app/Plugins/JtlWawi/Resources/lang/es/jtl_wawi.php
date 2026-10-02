<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : jtl_wawi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'JTL-Wawi',
    'intro' => 'Conecta JTL-Wawi como sistema de gestión de inventario principal: proyección de artículos y almacenes, lectura de existencias y traspaso idempotente de los asientos.',
    'beta_notice' => 'La API de JTL-Wawi está en programa beta/piloto. Tras el lanzamiento oficial, la disponibilidad puede depender de la edición JTL contratada y pasar a ser de pago.',

    'mode' => [
        'on_premise' => 'OnPremise',
        'cloud' => 'Pasarela cloud',
    ],

    'status' => [
        'draft' => 'Borrador',
        'pending_registration' => 'Registro pendiente',
        'active' => 'Activa',
        'blocked' => 'Bloqueada',
        'disconnected' => 'Desconectada',
    ],

    'field' => [
        'base_url' => 'URL base de la API Wawi',
        'base_url_help' => 'p. ej. https://wawi.example.local:5883/api/eazybusiness — la instancia API se crea en el administrador de JTL.',
        'api_version' => 'Versión de la API',
        'detected_version' => 'Versión de Wawi detectada',
        'company_id' => 'Empresa (x-companyid)',
        'company_id_help' => 'Opcional: mandante/empresa dentro de la Wawi.',
        'tenant_id' => 'ID de tenant',
        'client_id' => 'ID de cliente',
        'client_secret' => 'Secreto de cliente',
        'secret_keep' => '(sin cambios — dejar vacío)',
        'allow_private_network' => 'Permitir explícitamente direcciones privadas/internas',
        'allow_private_network_help' => 'Una Wawi OnPremise suele estar en su propia red. Esta autorización se audita y solo vale para esta conexión.',
        'last_sync' => 'Última sincronización',
        'last_error' => 'Último error',
    ],

    'stats' => [
        'linked_articles' => 'Artículos vinculados',
        'open_inbox' => 'Casos de asignación abiertos',
    ],

    'scopes' => [
        'missing' => 'Faltan scopes de lectura: :scopes — ajustar la aprobación de la app en JTL-Wawi y volver a comprobar el registro.',
        'missing_write' => 'Sin el scope de escritura (:scopes) el traspaso de existencias queda desactivado.',
    ],

    'registration' => [
        'heading' => 'Registro de la app',
        'explain' => 'Abrir en JTL-Wawi «Admin > Registro de apps» y luego iniciar aquí el registro. La clave API se emite una sola vez tras la aprobación y se guarda cifrada.',
        'waiting' => 'El registro espera la aprobación en JTL-Wawi. Tras confirmar, comprobar aquí el estado.',
    ],

    'connection' => [
        'heading' => 'Conexión',
    ],

    'sync' => [
        'section' => 'Sección',
        'counters' => 'Contadores',
        'warehouses' => 'Almacenes',
        'articles' => 'Artículos',
        'stocks' => 'Cambios de existencias',
    ],

    'warehouses' => [
        'heading' => 'Asignación de almacenes',
        'empty' => 'Aún no hay almacenes JTL proyectados — sincronizar primero.',
        'jtl' => 'Almacén JTL',
        'type' => 'Tipo',
        'flags' => 'Atributos',
        'local' => 'Almacén WorkDiary',
        'inactive' => 'inactivo',
        'lock_shipment' => 'Bloqueo de envío',
        'lock_availability' => 'Bloqueo de disponibilidad',
        'unmapped' => '— sin asignar —',
    ],

    'inventory' => [
        'heading' => 'Liderazgo de existencias',
        'explain' => 'Define qué sistema lidera las existencias. Volver a «local» importa las existencias de JTL como inventario de apertura.',
        'mode_local' => 'Local — WorkDiary gestiona las existencias por sí mismo.',
        'mode_external' => 'Externo — lidera JTL-Wawi; WorkDiary lee y traspasa los asientos.',
        'mode_read_only' => 'Solo lectura — lidera JTL-Wawi; WorkDiary solo muestra las existencias.',
    ],

    'action' => [
        'save' => 'Guardar',
        'sync_now' => 'Sincronizar ahora',
        'disconnect' => 'Desconectar',
        'start_registration' => 'Iniciar registro',
        'check_registration' => 'Comprobar aprobación',
        'map' => 'Asignar',
        'change_mode' => 'Cambiar modo',
    ],

    'confirm' => [
        'disconnect' => '¿Desconectar de verdad? Las asignaciones y proyecciones se conservan; las credenciales se eliminan.',
        'mode_change' => '¿Cambiar de verdad el modo de liderazgo de existencias?',
    ],

    'flash' => [
        'saved' => 'Conexión guardada.',
        'cloud_connected' => 'Conexión cloud establecida y token obtenido.',
        'cloud_failed' => 'Falló la conexión cloud — revisar credenciales e ID de tenant.',
        'registration_started' => 'Registro enviado — aprobarlo ahora en JTL-Wawi.',
        'registration_failed' => 'El registro falló.',
        'registration_pending' => 'La aprobación sigue pendiente.',
        'registration_accepted' => 'Aprobado — clave API guardada.',
        'registration_rejected' => 'El registro fue rechazado en JTL-Wawi.',
        'not_active' => 'La conexión no está activa.',
        'sync_done' => 'Sincronización finalizada.',
        'sync_failed' => 'Falló la sincronización (:reason).',
        'warehouse_mapped' => 'Asignación de almacén guardada.',
        'disconnected' => 'Conexión desconectada.',
        'disconnect_blocked' => 'No se puede desconectar: cambiar primero el liderazgo de existencias a «local».',
        'mode_unchanged' => 'Este modo ya está activo.',
        'mode_needs_connection' => 'El liderazgo externo de existencias requiere una conexión JTL activa.',
        'mode_needs_mapping' => 'El liderazgo externo de existencias requiere al menos un almacén JTL asignado.',
        'mode_changed' => 'Modo de liderazgo de existencias cambiado.',
        'mode_changed_with_takeover' => 'Modo cambiado — :booked correcciones de apertura importadas de JTL.',
        'takeover_done' => 'Inventario de apertura finalizado: :booked correcciones de :pairs pares.',
        'takeover_failed' => 'Falló el inventario de apertura (:reason).',
    ],
];
