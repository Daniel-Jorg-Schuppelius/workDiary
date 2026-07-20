<?php

return [
    'entity' => [
        'customers' => 'Clientes',
        'suppliers' => 'Proveedores',
        'articles' => 'Artículos',
        'projects' => 'Proyectos',
        'users' => 'Usuarios',
        'materials' => 'Materiales',
        'vehicles' => 'Vehículos',
        'scheduled_shifts' => 'Planes de turnos',
        'tours' => 'Rutas',
        'remote_sessions' => 'Sesiones de mantenimiento remoto',
    ],
    'template' => [
        'example_required' => 'Valor de ejemplo (obligatorio)',
        'example_optional' => 'Valor de ejemplo (opcional)',
        'download' => 'Descargar plantilla de ejemplo',
    ],

    'state' => [
        'preflight' => 'Comprobación previa',
        'awaitingApproval' => 'Pendiente de aprobación',
        'running' => 'En curso',
        'succeeded' => 'Correcto',
        'partial' => 'Parcial',
        'failed' => 'Fallido',
    ],
    'errorCode' => [
        'required' => 'Falta campo obligatorio',
        'format' => 'Error de formato',
        'unique' => 'Valor no único',
        'fkMissing' => 'Referencia no encontrada',
        'tooLong' => 'Valor demasiado largo',
        'outOfRange' => 'Valor fuera de rango',
        'persist' => 'Error de persistencia',
        'headerMissing' => 'Columna ausente',
        'headerUnknown' => 'Columna desconocida',
    ],
    'error' => [
        'required' => 'Falta el campo obligatorio :field.',
        'tooLong' => 'El campo :field supera la longitud máxima de :max caracteres.',
        'header' => [
            'missing' => 'Falta la columna obligatoria :column en el encabezado CSV.',
            'duplicate' => 'La columna :column aparece varias veces.',
        ],
        'format' => [
            'default' => 'El campo :field tiene un formato no válido (:reason).',
            'email' => 'Dirección de correo electrónico no válida.',
            'country' => 'El código de país debe tener de 2 a 3 letras mayúsculas (ISO 3166-1).',
            'currency' => 'El código de moneda debe tener 3 letras mayúsculas (ISO 4217).',
            'enum' => 'El valor no es un estado válido.',
            'parse' => 'No se pudo analizar el archivo: :reason',
            'xlsxUnreadable' => 'El archivo de Excel está dañado o no es un formato XLSX válido.',
            'xlsxEmpty' => 'La primera hoja de cálculo del archivo de Excel no contiene filas.',
            'date' => 'Fecha no válida (se esperaba p. ej. «28.05.2026, 09:42:09»).',
            'time' => 'Hora no válida (se esperaba HH:MM).',
            'status' => 'El valor no es un estado válido.',
        ],
        'outOfRange' => [
            'rowLimit' => 'Límite de filas (:max) superado — resto ignorado.',
        ],
        'fkMissing' => [
            'customer' => 'No se encontró ningún cliente con el número :number.',
            'user' => 'No se encontró ningún usuario con el correo :value.',
        ],
        'persist' => [
            'noBookingUser' => 'No se encontró ningún usuario imputable en la organización.',
        ],
    ],
];
