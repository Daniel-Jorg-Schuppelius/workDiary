<?php

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
    ],
    'correction' => [
        'action' => [
            'create' => 'Crear',
            'update' => 'Modificar',
            'delete' => 'Eliminar',
        ],
    ],
];
