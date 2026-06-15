<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : compliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'report' => [
        'title' => 'Cumplimiento del tiempo de trabajo',
        'nav' => 'Cumplimiento del tiempo de trabajo',
        'subtitle' => 'Infracciones de la ley de jornada laboral según el tiempo de trabajo realmente registrado.',
        'empty' => 'Sin infracciones en el periodo.',
        'thresholds_note' => 'Umbrales (ArbZG): máx. :daily neto/día · mín. :rest de descanso · máx. media :weekly/semana · pausas obligatorias de 30 min a partir de 6 h, 45 min a partir de 9 h.',
        'corrected' => 'corregido',
        'corrected_hint' => 'Existe una corrección de tiempo aprobada para este día.',
        'drilldown' => 'Abrir cierre diario',
        'filter' => [
            'kind' => 'Tipo de infracción',
            'all' => 'Todos los tipos',
        ],
        'kpi' => [
            'total' => 'Infracciones totales',
            'employees' => 'Empleados afectados',
        ],
        'kind' => [
            'maxDailyHours' => 'Jornada diaria máxima',
            'restPeriod' => 'Tiempo de descanso',
            'breakMissing' => 'Pausa obligatoria',
            'maxWeeklyHours' => 'Jornada semanal máxima',
        ],
        'severity' => [
            'error' => 'Infracción',
            'warning' => 'Aviso',
        ],
        'col' => [
            'date' => 'Fecha',
            'kind' => 'Tipo',
            'value' => 'Valor',
            'threshold' => 'Umbral',
            'severity' => 'Gravedad',
        ],
        'csv' => [
            'employee' => 'Empleado',
            'date' => 'Fecha',
            'kind' => 'Tipo',
            'severity' => 'Gravedad',
            'value' => 'Valor',
            'threshold' => 'Umbral',
            'corrected' => 'Corregido',
            'yes' => 'sí',
        ],
    ],
];
