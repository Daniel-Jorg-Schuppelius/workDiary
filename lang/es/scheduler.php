<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scheduler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Tareas programadas',
        'subtitle' => 'Pausar, reprogramar y supervisar los trabajos del registro — sin cambios de código.',
        'help' => 'Solo trabajos registrados, solo horarios permitidos',
        'help_text' => 'Todos los trabajos provienen del registro del lado del servidor. La reprogramación se limita a los intervalos permitidos por trabajo; los cambios se auditan y surten efecto en el siguiente tick del planificador.',
        'reschedule' => 'Reprogramar trabajo',
    ],
    'field' => [
        'job' => 'Trabajo',
        'plan' => 'Planificación',
        'last_run' => 'Última ejecución',
        'next_due' => 'Próximo vencimiento',
        'failures' => 'Fallos consecutivos',
        'actions' => 'Acciones',
        'cadence_type' => 'Intervalo',
        'time' => 'Hora',
        'day' => 'Día',
        'expression' => 'Expresión cron',
    ],
    'action' => [
        'reschedule' => 'Reprogramar',
        'pause' => 'Pausar',
        'resume' => 'Reanudar',
        'reset' => 'Restablecer al valor predeterminado',
        'test_run' => 'Iniciar ejecución de prueba',
        'save' => 'Guardar',
    ],
    'state' => [
        'paused' => 'Pausado',
        'success' => 'Correcto',
        'failed' => 'Fallido',
        'never_ran' => 'Nunca ejecutado',
    ],
    'source' => [
        'default' => 'Plan predeterminado',
        'setting' => 'Desde una configuración',
        'override' => 'Reprogramado manualmente',
    ],
    'cadence' => [
        'everyMinute' => 'Cada minuto',
        'everyFiveMinutes' => 'Cada 5 minutos',
        'everyFifteenMinutes' => 'Cada 15 minutos',
        'everyThirtyMinutes' => 'Cada 30 minutos',
        'hourly' => 'Cada hora',
        'dailyAt' => 'Diariamente a las',
        'weeklyOn' => 'Semanalmente el',
        'monthlyOn' => 'Mensualmente el',
        'cron' => 'Expresión cron',
    ],
    'criticality' => [
        'core' => 'Operación central',
        'integration' => 'Integración',
        'housekeeping' => 'Limpieza',
    ],
    'hint' => [
        'time' => 'Solo para planes diarios/semanales/mensuales.',
        'day' => 'Día de la semana 0–6 (0 = domingo) o día del mes 1–31.',
        'expression' => 'Solo para operadores: minuto hora día mes día-semana.',
        'allowlist' => 'Duración prevista aprox. :runtime min. El trabajo se ejecuta con protección contra solapamientos; los intervalos demasiado cortos se rechazan en el servidor.',
    ],
    'flash' => [
        'rescheduled' => 'El trabajo :job ha sido reprogramado.',
        'paused' => 'El trabajo :job ha sido pausado.',
        'resumed' => 'El trabajo :job ha sido reanudado.',
        'reset' => 'El trabajo :job vuelve a usar el plan predeterminado.',
        'test_run_queued' => 'La ejecución de prueba de :job se ha puesto en cola.',
        'test_run_cooldown' => 'Espere — solo es posible una ejecución de prueba por trabajo cada :minutes minutos.',
    ],
];
