<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
        'attendances' => 'Fichajes',
        'project_times' => 'Tiempos de proyecto',
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
        'periodLocked' => 'Periodo bloqueado',
        'skipped' => 'Omitido',
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
            'project' => 'No se encontró ningún proyecto «:value» — fila enviada a la bandeja de asignación.',
        ],
        'persist' => [
            'noBookingUser' => 'No se encontró ningún usuario imputable en la organización.',
        ],
        // MVP-438: bloqueo GoBD — sin sobrescritura silenciosa de periodos revisados.
        'periodLocked' => [
            'attendance' => 'El día :date está bloqueado por el cierre diario o la aprobación mensual — fila omitida.',
            'projectTime' => 'El periodo :date ya está cerrado/exportado — fila omitida.',
        ],
        // MVP-438: filas de aviso iCal (mapeo deliberadamente conservador).
        'ical' => [
            'allDay' => 'Evento de todo el día «:event» omitido (no computable como presencia).',
            'noTime' => 'Evento «:event» sin hora omitido.',
            'category' => 'Evento «:event» fuera de la lista de categorías permitidas omitido.',
            'transparent' => 'Evento «:event» marcado como libre/ausente omitido.',
            'recurring' => 'Evento recurrente «:event»: solo se importó la instancia base (la expansión de la serie vendrá después).',
            'unsupportedEntity' => 'La importación iCal no es compatible con este tipo de importación.',
        ],
    ],
];
