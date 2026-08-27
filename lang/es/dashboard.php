<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dashboard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'width' => [
        'half' => 'Media anchura',
        'full' => 'Anchura completa',
    ],

    'group' => [
        'overview' => 'Resumen',
        'time' => 'Tiempo',
        'tasks' => 'Tareas',
        'activity' => 'Actividad',
        'deadlines' => 'Plazos',
        'finance' => 'Finanzas',
        'operations' => 'Operación',
    ],

    'widget' => [
        'personal_kpis' => [
            'description' => 'Entradas abiertas, trabajos en curso, turnos y guardias próximos.',
        ],
        'team_kpis' => [
            'description' => 'Entradas abiertas y en curso del equipo, archivadas hoy, plantilla.',
        ],
        'today_shifts' => [
            'description' => 'Tus turnos de hoy.',
        ],
        'upcoming_shifts' => [
            'description' => 'Tus próximas guardias y turnos.',
        ],
        'emergencies' => [
            'description' => 'Intervenciones de guardia próximas.',
        ],
        'scheduled_shifts' => [
            'description' => 'Cuadrante de los próximos siete días.',
        ],
        'open_issues' => [
            'description' => 'Puntos abiertos asignados a ti — por fecha de vencimiento.',
        ],
        'recent_entries' => [
            'description' => 'Tus entradas editadas más recientemente.',
        ],
        'recent_comments' => [
            'description' => 'Nuevos comentarios en tus entradas.',
        ],
        'recent_attachments' => [
            'description' => 'Nuevos adjuntos en tus entradas.',
        ],
        'team_activity' => [
            'description' => 'Los últimos comentarios del equipo.',
        ],
        'finance' => [
            'description' => 'Gastos y viajes del mes; para quien aprueba, además la cola pendiente.',
        ],
        'vacation' => [
            'description' => 'Solicitudes de vacaciones abiertas y días aprobados este año.',
        ],
        'onboarding' => [
            'description' => 'Progreso de la lista de puesta en marcha.',
        ],
        'attendance_clock' => [
            'description' => 'Fichar entrada y salida, pausas y estados intermedios.',
        ],
        'bookmarks' => [
            'description' => 'Tus marcadores guardados.',
        ],
        'data_protection' => [
            'description' => 'Revisiones del registro vencidas y solicitudes de interesados abiertas.',
        ],
        'operations_tasks' => [
            'description' => 'Tareas de operación abiertas por urgencia.',
        ],
        'stopwatch' => [
            'description' => 'El cronómetro en marcha con proyecto y descripción.',
        ],
        'flex_balance' => [
            'description' => 'Saldo de horas flexibles del último mes cerrado, con semáforo.',
        ],
        'time_accounts' => [
            'description' => 'Saldos de tus cuentas de tiempo (horas extra, cuentas especiales).',
        ],
        'time_corrections' => [
            'description' => 'Tus solicitudes de corrección en curso o enviadas.',
        ],
        'reminders' => [
            'description' => 'Pendientes de gastos, viajes y vacaciones — los mismos que bajo la campana.',
        ],
        'kanban_status' => [
            'description' => 'Cuántos de tus encargos hay en cada columna Kanban.',
        ],
        'service_tickets' => [
            'description' => 'Tickets abiertos asignados a ti.',
        ],
        'chat_unread' => [
            'description' => 'Mensajes no leídos por canal.',
        ],
        'approvals' => [
            'description' => 'Gastos y solicitudes de vacaciones a la espera de tu decisión.',
        ],
        'asset_compliance' => [
            'description' => 'Inspecciones vencidas y próximas del calendario de inspección.',
        ],
        'asset_blocks' => [
            'description' => 'Objetos actualmente bloqueados, con el motivo.',
        ],
        'contract_deadlines' => [
            'description' => 'Obligaciones y plazos contractuales de las próximas semanas.',
        ],
        'leasing_deadlines' => [
            'description' => 'Plazos de rescisión, devolución y prórroga de los expedientes de leasing.',
        ],
        'safety_due' => [
            'description' => 'Revisiones próximas de evaluaciones de riesgos y reconocimientos médicos.',
        ],
        'training_due' => [
            'description' => 'Tus obligaciones de formación e instrucción abiertas.',
        ],
        'open_times' => [
            'description' => 'Tiempos facturables que aún no están en ninguna factura.',
        ],
        'open_items' => [
            'description' => 'Cuentas por cobrar y pagar abiertas, con la parte vencida.',
        ],
        'tax_filings' => [
            'description' => 'Próximos plazos de declaración en contabilidad.',
        ],
        'integration_inbox' => [
            'description' => 'Partidas importadas pendientes de asignación.',
        ],
        'backup_status' => [
            'description' => 'Cómo de recientes son las copias de seguridad, por fuente.',
        ],
        'plugin_health' => [
            'description' => 'Plugins cuya última comprobación de estado falló.',
        ],
    ],

    'preset' => [
        'classic' => [
            'label' => 'Panel clásico',
            'description' => 'Indicadores y marcadores arriba y, debajo, las cuatro secciones Resumen, Tareas, Actividad y Finanzas: el panel tal como era antes de la conversión en tarjetas, más el reloj de fichar.',
        ],
    ],
];
