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
            'category' => 'Ámbito',
            'all_categories' => 'Todos los ámbitos',
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
            'frameTime' => 'Horario marco',
            'coreTime' => 'Horario central',
            'entryBreakMissing' => 'Pausa obligatoria (tiempo de proyecto)',
            'missingCheckout' => 'Falta fichaje de salida',
            'freeDayStamp' => 'Fichaje en día libre',
            'absenceStamp' => 'Fichaje durante ausencia',
            'attendanceFrameTime' => 'Marco horario (fichajes)',
            'lateRecording' => 'Registro tardío (MiLoG)',
            'sixMonthAverage' => 'Promedio semestral (§ 3 ArbZG)',
            'nightWork' => 'Trabajo nocturno superior a 8 h (§ 6 ArbZG)',
            'substituteRestDay' => 'Falta día de descanso sustitutorio (§ 11 ArbZG)',
            'freeSundays' => 'Domingos libres insuficientes (§ 11 ArbZG)',
            // Feature 144 (MVP-719): Lenk-/Ruhezeiten (VO (EG) 561/2006 / FPersV).
            'dailyDriving' => 'Tiempo de conducción diario (art. 6 Regl. 561/2006)',
            'weeklyDriving' => 'Tiempo de conducción semanal (art. 6 Regl. 561/2006)',
            'fortnightDriving' => 'Tiempo de conducción bisemanal (art. 6 Regl. 561/2006)',
            'drivingBreakMissing' => 'Falta pausa de conducción (art. 7 Regl. 561/2006)',
            'dailyRest' => 'Descanso diario (art. 8 Regl. 561/2006)',
            'weeklyRest' => 'Descanso semanal (art. 8 Regl. 561/2006)',
        ],
        'unit' => [
            'days' => '{1} :count día|[2,*] :count días',
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
    'history' => [
        'title' => 'Infracciones de cumplimiento',
        'nav' => 'Historial de infracciones',
        'subtitle' => 'Infracciones de la ArbZG persistidas con estado de tratamiento y acuse.',
        'to_report' => 'Informe detallado',
        'to_dashboard' => 'Panel',
        'filter' => [
            'status' => 'Estado',
            'all' => 'Todos los estados',
            'category' => 'Categoría',
        ],
        'col' => [
            'employee' => 'Empleado',
            'status' => 'Estado',
        ],
        'empty' => 'No hay infracciones persistidas.',
        'note_placeholder' => 'Motivo (obligatorio para «aceptado»)',
        'btn' => [
            'acknowledge' => 'Confirmar',
            'accept' => 'Aceptar',
            'correction' => 'Solicitud de corrección',
        ],
        'category' => [
            'arbzg' => 'ArbZG',
            'plausibility' => 'Casos sin aclarar',
            'drivingTime' => 'Tiempos de conducción',
        ],
        'acknowledged' => 'Infracción actualizada.',
        'error' => [
            'invalid_status' => 'Estado de destino no válido.',
            'not_acknowledgeable' => 'Esta infracción ya no se puede confirmar.',
            'note_required' => 'Se requiere un motivo para «aceptado».',
        ],
    ],
    'milog' => [
        'button' => 'Justificante MiLoG (aduana)',
        'csv' => [
            'employee' => 'Empleado',
            'personnel_number' => 'Número de personal',
            'date' => 'Fecha',
            'start' => 'Inicio',
            'end' => 'Fin',
            'breaks' => 'Pausas (min)',
            'duration' => 'Duración',
        ],
    ],
    'driving' => [
        'button' => 'Justificante tiempos de conducción',
        'title' => 'Justificante de tiempos de conducción y descanso',
        'thresholds_note' => 'Tiempos de conducción/descanso (Regl. (CE) 561/2006 / FPersV): máx. 9 h de conducción/día (10 h dos veces por semana) · 56 h/semana · 90 h/dos semanas · pausa de 45 min tras 4,5 h (divisible 15 + 30) · descanso 11 h/día (máx. 3×/semana 9 h) · 45 h/semana (24 h con compensación).',
        'disclaimer' => 'La base de datos son los viajes registrados (libro de ruta) con vehículos marcados; no se leen datos del tacógrafo/DTCO. No constituye asesoramiento jurídico.',
        'csv' => [
            'driver' => 'Conductor',
            'personnel_number' => 'Número de personal',
            'date' => 'Fecha',
            'vehicles' => 'Vehículos',
            'start' => 'Primera salida',
            'end' => 'Última llegada',
            'driving' => 'Tiempo de conducción',
            'longest_stint' => 'Periodo de conducción más largo sin pausa',
            'breaks' => 'Pausas (min)',
            'rest_before' => 'Descanso previo',
            'findings' => 'Hallazgos',
        ],
        'badge' => [
            'label' => 'Tiempo de conducción',
            'remaining' => ':remaining disponibles',
            'until_break' => 'Pausa en :until',
            'break_due' => 'Pausa pendiente',
            'exhausted' => 'Tiempo de conducción diario agotado',
            'title' => 'Tiempo de conducción diario restante :daily (límite :limit) · próxima pausa en :until · resto semanal :weekly · dos semanas :fortnight',
        ],
    ],
];
