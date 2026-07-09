<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : prerequisites.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'blocked' => [
        'missing_required' => 'Falta un requisito',
        'missing_optional' => 'Aviso',
        'not_licensed' => 'Sin licencia',
        'not_allowed' => 'Sin permiso',
        'provider_unsupported' => 'No compatible con el proveedor',
    ],
    'contact_role' => 'Póngase en contacto con: :role',
    'warehouses' => [
        'missing' => 'El recuento y la contabilización requieren al menos un almacén.',
        'cta' => 'Gestionar almacenes',
    ],
    'dispatch' => [
        'cta' => 'Ir al panel de disposición del pedido',
    ],
    'mappings' => [
        'hint' => 'Las asignaciones se crean automáticamente durante la importación o al resolver elementos de la bandeja de entrada (sincronización de plugins e importación CSV).',
        'cta' => 'Ir a la bandeja de integraciones',
    ],
    'shift_types' => [
        'missing' => 'Aún no se han creado tipos de turno — sin tipo, la planificación de turnos es limitada.',
        'cta' => 'Crear tipos de turno',
        'dialog_hint' => 'Todavía no hay tipos de turno. El turno se guarda sin tipo; la administración gestiona los tipos mediante «Tipos de turno» en el plan de turnos.',
    ],
];
