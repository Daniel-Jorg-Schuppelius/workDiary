<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : redmine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Importa incidencias de proyectos de Redmine seleccionados como tareas en proyectos de WorkDiary y, si se desea, los registros de tiempo como tiempos registrados, solo lectura: título, descripción, estado mediante «cerrada» y vencimiento. Los conflictos y casos por revisar van a la bandeja de entrada de integraciones.',
    ],
    'source' => 'Instancia',
    'task_link' => 'Abrir en Redmine',
    'health' => [
        'not_configured' => 'Redmine no está configurado (falta la URL base o la clave de API).',
        'not_connected' => 'La conexión con Redmine aún no se ha comprobado.',
    ],
    'settings' => [
        'base_url' => 'URL base',
        'base_url_help' => 'Dirección de la instancia de Redmine, p. ej. https://redmine.example.com.',
        'api_key' => 'Clave de API',
        'api_key_help' => 'En Redmine en Mi cuenta → Clave de acceso a la API; la API REST debe estar activada en la administración. La cuenta necesita en los proyectos un rol que pueda ver incidencias y tiempo dedicado, con visibilidad «todos».',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una URL base con una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
        'default_billable' => 'Importar tiempos como facturables',
        'default_billable_help' => 'Desactivado, los tiempos importados nunca son facturables; activado, se aplica la configuración del proyecto de destino.',
        'time_window_days' => 'Ventana de tiempo (días)',
        'time_window_days_help' => 'Cuántos días hacia atrás lee cada ejecución los registros de tiempo. Los anteriores no se tocan.',
    ],
    'time' => [
        'fallback' => 'Registro de tiempo de Redmine',
    ],
    'flash' => [
        'times_enabled' => 'Importación de tiempos activada para esta asignación.',
        'times_disabled' => 'Importación de tiempos desactivada para esta asignación. Los tiempos ya importados se conservan.',
        'user_invalid' => 'Elija un usuario de su organización.',
        'user_mapped' => 'Usuario asignado. La próxima sincronización registra los tiempos pendientes.',
        'user_unmapped' => 'Asignación eliminada. Los tiempos ya registrados se conservan.',
    ],
    'times' => [
        'title' => 'Registros de tiempo',
        'hint' => 'Para cada asignación se pueden importar los registros de tiempo de Redmine de los últimos :days días. Redmine solo conoce día y horas, por eso se registran como duración sin hora en el proyecto de destino; la tarea es la de la incidencia vinculada.',
        'col' => [
            'project' => 'Proyecto de Redmine',
            'last_run' => 'Última importación de tiempos',
            'import' => 'Importar tiempos',
        ],
        'counters' => ':created nuevos · :updated modificados · :unresolved_users esperando usuario · :removed eliminados',
        'enabled' => 'Activado',
        'disabled' => 'Desactivado',
        'empty' => 'Aún no hay asignaciones de proyectos',
    ],
    'users' => [
        'title' => 'Asignación de usuarios',
        'hint' => 'Los registros de tiempo solo se registran para usuarios de Redmine asignados, nunca en silencio para otra persona. La próxima sincronización registra los pendientes tras la asignación.',
        'waiting' => ':name (:count registros pendientes)',
        'map' => 'Asignar',
        'none_pending' => 'Ningún registro de tiempo espera una asignación de usuario',
        'mapped' => 'Asignados',
        'mapping' => 'Usuario de Redmine #:redmine → :user',
        'unmap' => 'Eliminar asignación',
        'confirm_unmap' => '¿Eliminar la asignación? Los tiempos ya registrados se conservan; los nuevos registros vuelven a esperar.',
    ],
];
