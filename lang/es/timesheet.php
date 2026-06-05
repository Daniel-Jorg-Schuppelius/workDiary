<?php

return [
    'titles' => [
        'index' => 'Hoja de horas',
        'show' => 'Hoja de horas n.º:id',
    ],
    'fields' => [
        'date' => 'Fecha',
        'project' => 'Proyecto',
        'user' => 'Empleado',
        'status' => 'Estado',
        'started_at' => 'Inicio',
        'ended_at' => 'Fin',
        'break_minutes' => 'Pausa (min)',
        'duration' => 'Duración',
        'kind' => 'Tipo',
        'description' => 'Descripción',
        'notes' => 'Notas',
    ],
    'totals' => [
        'work' => 'Total trabajo',
        'break' => 'Total pausa',
        'material_net' => 'Total material (neto)',
    ],
    'sections' => [
        'entries' => 'Registros de tiempo',
        'materials' => 'Materiales',
        'customer_release' => 'Aprobación del cliente',
        'notes' => 'Notas',
    ],
    'signature' => [
        'signed_at' => 'Firmado el :datetime',
        'ip' => 'IP :ip',
        'hash' => 'SHA-256: :hash',
        'none' => '— sin firma —',
    ],
];
