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
        'checkin' => 'Check-in (QR/NFC)',
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
    'checkpoint_kind' => [
        'site' => 'Ubicación',
        'vehicle' => 'Vehículo',
    ],
    'checkin' => [
        'title' => 'Check-in',
        'subtitle' => 'Entrada y salida con el código de la ubicación o del vehículo.',
        'state' => [
            'in' => 'Ha fichado la entrada a las :time.',
            'out' => 'Ahora no tiene la entrada fichada.',
        ],
        'action' => [
            'in' => 'Entrada',
            'out' => 'Salida',
        ],
        'location_hint' => 'Al fichar se comprueba una vez la posición (radio de :radius m). No se guarda.',
        'flash' => [
            'in' => 'Entrada en «:name» registrada.',
            'out' => 'Salida en «:name» registrada.',
        ],
        'error' => [
            'already_in' => 'Ya ha fichado la entrada.',
            'not_in' => 'No ha fichado la entrada.',
            'no_center' => 'Este punto tiene radio pero no ubicación. Contacte con la administración.',
            'location_required' => 'Este punto requiere su posición.',
            'too_far' => 'Está a :distance m; se permiten :radius m.',
            'location_denied' => 'No se pudo determinar la posición. Permite el acceso a la ubicación.',
        ],
    ],
];
