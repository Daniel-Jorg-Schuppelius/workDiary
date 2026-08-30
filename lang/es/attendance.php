<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : attendance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Estados intermedios (MVP-532): teletrabajo/gestión de servicio.
    'intermediate' => [
        'homeoffice' => 'Teletrabajo',
        'errand' => 'Gestión de servicio',
        'start_homeoffice' => 'Iniciar teletrabajo',
        'end_homeoffice' => 'Finalizar teletrabajo',
        'start_errand' => 'Iniciar gestión',
        'end_errand' => 'Finalizar gestión',
    ],
    'status' => [
        'open' => 'Abierto',
        'closed' => 'Cerrado',
        'auto_closed' => 'Cerrado automáticamente',
        'adjusted' => 'Ajustado',
        'cancelled' => 'Cancelado',
    ],
    'source' => [
        'clock' => 'Fichaje',
        'manual' => 'Manual',
        'import' => 'Importación',
        'auto_close' => 'Cierre automático',
        'terminal' => 'Terminal',
        'phone' => 'Teléfono',
        'learning' => 'Tiempo de aprendizaje',
    ],
    'correction' => [
        'action' => [
            'create' => 'Crear',
            'update' => 'Modificar',
            'delete' => 'Eliminar',
        ],
    ],
    'error' => [
        'target_day_locked' => 'El día de destino está cerrado o el mes aprobado: solicite una corrección de tiempo.',
        'duration_too_long' => 'Un fichaje no puede superar las :hours horas.',
    ],
];
