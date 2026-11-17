<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : delegation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vertretung bei Abwesenheit (Feature 202, MVP-1647).
return [
    'title' => 'Sustituciones',
    'subtitle' => 'Quién sustituye a quién durante una ausencia. La persona sustituta ve el trabajo asignado en la cola de trabajo y recibe sus notificaciones, pero no obtiene derechos adicionales.',
    'create' => 'Crear sustitución',
    'from_absence' => 'Crear sustitución',
    'filter' => [
        'state' => 'Periodo',
        'current' => 'En curso y previstas',
        'past' => 'Finalizadas y vencidas',
        'all' => 'Toda la organización',
    ],
    'empty' => [
        'title' => 'No hay sustituciones',
        'message' => 'Aquí aparecen las sustituciones en las que usted participa.',
    ],
    'column' => [
        'represented' => 'Persona sustituida',
        'deputy' => 'Sustituto',
        'period' => 'Periodo',
        'state' => 'Estado',
        'work' => 'Asume',
    ],
    'open_end' => 'hasta nuevo aviso',
    'state' => [
        'active' => 'En curso',
        'upcoming' => 'Prevista',
        'past' => 'Vencida',
        'ended' => 'Finalizada',
    ],
    'ended_by' => 'Finalizada por :name el :date',
    'end' => 'Finalizar sustitución',
    'end_confirm' => '¿Finalizar ahora la sustitución? El trabajo volverá a estar solo en manos de la persona sustituida.',
    'dialog' => [
        'title' => 'Crear sustitución',
        'submit' => 'Crear sustitución',
        'no_rights_hint' => 'La persona sustituta ve y tramita el trabajo asignado y recibe sus notificaciones. No obtiene derechos adicionales ni acceso a tiempos, ausencias o expediente personal.',
        'people' => 'Personas',
        'deputy_hint' => 'Prerrellenado con el suplente designado de la persona.',
        'choose_deputy' => 'Seleccione',
        'from' => 'Desde',
        'until' => 'Hasta',
        'until_hint' => 'Sin fecha de fin, la sustitución rige hasta que alguien la finalice.',
        'kind' => 'Motivo',
        'note' => 'Nota',
        'work_heading' => 'Esto asume el sustituto de :name',
        'work_none' => 'Actualmente no hay trabajo asignado pendiente',
        'work_hint' => 'La persona sustituida sigue siendo responsable; después no hay nada que reasignar.',
    ],
    'banner' => [
        'represents' => 'Usted sustituye a :names.',
        'represented_by' => 'Sustituido por :names.',
        'person_until' => ':name (hasta el :date)',
        'work_queue' => 'Ir a la cola de trabajo',
        'manage' => 'Sustituciones',
    ],
    'notification' => [
        'on_behalf_of' => 'En sustitución de :name:',
        'created' => 'Sustituye a :name del :from al :until.',
        'created_open' => 'Sustituye a :name desde el :from hasta nuevo aviso.',
    ],
    'flash' => [
        'created' => 'Sustitución por :name creada.',
        'ended' => 'Sustitución finalizada.',
    ],
    'error' => [
        'past_start' => 'Una sustitución empieza como muy pronto hoy.',
        'end_before_start' => 'El fin es anterior al inicio.',
        'absence_mismatch' => 'La ausencia no pertenece a esta persona o no está aprobada.',
        'overlap' => ':name ya tiene una sustitución en este periodo.',
        'deputy_absent' => ':name está sustituido en este periodo y no puede sustituir.',
        'represented_is_deputy' => ':name sustituye a alguien en este periodo; no existen sustituciones en cadena.',
        'already_ended' => 'Esta sustitución ya ha finalizado.',
        'foreign_organization' => 'Las sustituciones solo son posibles dentro de la propia organización.',
        'self' => 'Una persona no puede sustituirse a sí misma.',
        'deactivated' => 'Las cuentas desactivadas no pueden sustituir ni ser sustituidas.',
    ],
    // Traspaso definitivo sin baja y selección múltiple en la lista de pedidos (MVP-1648).
    'handover' => [
        'action' => 'Traspasar responsabilidades',
        // Personenwahl auf der Seite „Vertretungen“ (Einstieg ohne Mitgliederliste).
        'choose_title' => '¿De quién quiere traspasar las responsabilidades?',
        'choose_hint' => 'Elija la persona cuyo trabajo pasa de forma definitiva a sus sucesores. Después elegirá el sucesor de cada área.',
        'choose_empty' => 'No hay ninguna persona cuyo trabajo pueda traspasar.',
        'title' => 'Traspasar las responsabilidades de :name',
        'intro' => 'Traspaso definitivo sin baja: las áreas elegidas pasan de forma permanente al sucesor; la persona sigue siendo miembro. Para una ausencia temporal, lo adecuado es una sustitución.',
        'areas' => 'Áreas y sucesor',
        'hint' => 'Un sucesor por área o ninguno. Solo se traspasa lo que al guardar está abierto y asignado a la persona; se registra la cantidad por área.',
        'submit' => 'Traspasar definitivamente',
        'empty' => 'Actualmente no hay nada asignado a esta persona que se pueda traspasar.',
        'single_records' => 'Los pedidos individuales se traspasan en la lista de pedidos con la selección «Cambiar responsable».',
        'help' => 'Ayuda',
        'flash' => '{0} No se ha traspasado nada.|{1} Se ha traspasado un elemento de :name.|[2,*] Se han traspasado :count elementos de :name.',
        'area' => [
            'assigned_orders' => '{1} Un pedido asignado|[2,*] :count pedidos asignados',
            'assigned_tasks' => '{1} Una tarea abierta|[2,*] :count tareas abiertas',
            'assigned_service_tickets' => '{1} Un ticket de servicio asignado|[2,*] :count tickets de servicio asignados',
            'assigned_procedure_runs' => '{1} Una ejecución de procedimiento en curso|[2,*] :count ejecuciones de procedimiento en curso',
        ],
        'error' => [
            'inactive' => 'Solo se traspasa el trabajo de miembros activos; para una baja se usa el diálogo de baja.',
            'successor' => 'El sucesor debe ser otro miembro activo de la misma organización.',
            'none' => 'Elija un sucesor al menos para un área.',
        ],
    ],
    'reassign' => [
        'selected' => ':n pedidos seleccionados',
        'choose' => 'Elegir nuevo responsable…',
        'submit' => 'Cambiar responsable',
        'select_entry' => 'Seleccionar el pedido «:title»',
        'unknown_person' => 'Esta persona no existe en su organización o está desactivada.',
        'done' => '{0} No se ha cambiado ningún responsable.|{1} Un pedido está ahora con :name.|[2,*] :count pedidos están ahora con :name.',
        'skipped' => '{1} Se ha omitido un pedido (sin permiso de edición, ya responsable o falta cualificación).|[2,*] Se han omitido :count pedidos (sin permiso de edición, ya responsable o falta cualificación).',
    ],
    'demo' => [
        'note' => 'Ejemplo: sustitución durante un curso de formación.',
    ],
];
