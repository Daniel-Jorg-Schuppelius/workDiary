<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : allocation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Repartir el tiempo',
    'entry_duration' => 'Duración del registro',
    'hint' => 'Las filas vacías se ignoran; vaciar todas las filas elimina el reparto. La suma de las partes no puede superar la duración.',
    'target' => 'Destino',
    'minutes' => 'Minutos',
    'quantity' => 'Cantidad',
    'comment' => 'Comentario',
    'none_option' => '— sin parte —',
    'type' => [
        'task' => 'Tareas',
        'asset' => 'Activos',
        'project' => 'Proyectos',
        'cost_center' => 'Centros de coste',
        'site' => 'Ubicaciones',
        'vehicle' => 'Vehículos',
        'activity_category' => 'Actividades',
    ],
    'action' => [
        'split' => 'Repartir',
        'save' => 'Guardar reparto',
    ],
    'flash' => [
        'saved' => 'Reparto guardado.',
    ],
    'error' => [
        'locked' => 'El registro está bloqueado (:reason) — reparto no posible.',
        'invalid_target' => 'Destino de reparto no válido o ajeno.',
        'minutes_min' => 'Cada parte necesita al menos un minuto.',
        'sum_exceeds' => 'La suma de las partes (:sum min) supera la duración del registro (:max min).',
    ],
    // Dimensiones libres del inquilino (MVP-514 P2)
    'dimensions' => [
        'nav' => 'Dimensiones de tiempo',
        'title' => 'Dimensiones de tiempo libres',
        'intro' => 'Dimensiones personalizadas para el reparto del tiempo (p. ej. pedidos ERP) — solo para destinos sin un modelo WorkDiary existente. El ID externo ancla una futura sincronización con proveedores.',
        'new_type' => 'Nuevo tipo de dimensión',
        'code' => 'Código',
        'name' => 'Nombre',
        'create_type' => 'Crear tipo',
        'enabled' => 'Activo',
        'disabled' => 'Inactivo',
        'no_types' => 'Aún no hay tipos de dimensión.',
        'no_values' => 'Aún no hay valores.',
        'external_id' => 'ID externo',
        'validity' => 'Validez',
        'valid_from' => 'Válido desde',
        'valid_until' => 'Válido hasta',
        'create_value' => 'Crear valor',
        'delete_value' => 'Eliminar',
        'flash' => [
            'type_created' => 'Tipo de dimensión creado.',
            'type_enabled' => 'Tipo de dimensión activado.',
            'type_disabled' => 'Tipo de dimensión desactivado.',
            'value_created' => 'Valor creado.',
            'value_deleted' => 'Valor eliminado.',
        ],
    ],
];
