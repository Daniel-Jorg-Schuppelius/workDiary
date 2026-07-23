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
    // Nombres legibles de los trabajos (claves del registro, anidadas por la notación de puntos);
    // añadir los nuevos trabajos aquí en todos los idiomas — si no, respaldo = clave.
    'job' => [
        'calendly' => ['backfill' => 'Sincronización de citas de Calendly'],
        'ai' => ['maintenance' => 'Mantenimiento de IA (salud de proveedores, limpieza de sugerencias)'],
        'archive' => ['run' => 'Ejecución de archivado'],
        'attendance' => ['close_open' => 'Cerrar fichajes olvidados'],
        'audit' => ['verify' => 'Verificar la cadena de auditoría'],
        'backup' => [
            'check_restore' => 'Comprobación de copias de seguridad',
            'cloud-run' => 'Ejecución de la copia de seguridad en la nube',
            'cloud-verify' => 'Comprobación de la copia de seguridad en la nube',
        ],
        'billbee' => ['sync' => 'Sincronización Billbee'],
        'carddav' => ['sync' => 'Sincronización CardDAV'],
        'catalog' => ['fetch_due' => 'Obtener fuentes de catálogo'],
        'chat' => [
            'send_reminders' => 'Enviar recordatorios de chat',
            'send_scheduled' => 'Enviar mensajes de chat programados',
        ],
        'claims' => ['escalate' => 'Escalado de plazos de reclamaciones'],
        'cloud-intake' => ['sync' => 'Recuperar la recepción de documentos en la nube'],
        'compliance' => ['scan_findings' => 'Analizar hallazgos de cumplimiento'],
        'events' => [
            'check_certificates' => 'Comprobar la caducidad de certificados',
            'dispatch_reminders' => 'Enviar recordatorios de eventos',
            'materialize_recurrences' => 'Materializar eventos recurrentes',
        ],
        'domain' => [
            'sync' => 'Sincronización de dominios',
            'events' => 'Recuperar eventos de dominio',
        ],
        'easybill' => ['sync' => 'Recuperación de documentos easybill'],
        'integration' => ['purge_inbox' => 'Depurar la bandeja de integraciones'],
        'inventory' => ['cycle_counts' => 'Iniciar inventario cíclico', 'expiring_lots' => 'Vigilancia de caducidad (lotes por vencer)'],
        'invoicing' => ['recurring' => 'Generar borradores de facturas recurrentes'],
        'jtl' => ['sync' => 'Sincronización JTL Wawi'],
        'lexoffice' => [
            'sync_articles' => 'Sincronizar artículos de Lexoffice',
            'sync_contacts' => 'Sincronizar contactos de Lexoffice',
            'sync_vouchers' => 'Sincronizar comprobantes de Lexoffice',
        ],
        'location' => ['purge_points' => 'Depurar puntos de ubicación sin procesar'],
        'mail' => ['poll' => 'Consultar correo entrante'],
        'maintenance' => ['scan_due' => 'Comprobar planes de mantenimiento vencidos'],
        'notifications' => ['scan_deadlines' => 'Comprobar plazos y notificar'],
        'openproject' => [
            'import' => 'Importación de OpenProject',
            'push' => 'Transferir tiempos a OpenProject',
        ],
        'operations' => ['scan' => 'Sincronizar tareas operativas'],
        'orgamax' => ['sync' => 'Sincronización orgaMAX'],
        'payroll' => ['import_minimum_wages' => 'Importar salarios mínimos de la UE'],
        'plans' => ['purge' => 'Depurar datos de módulos degradados'],
        'plugin' => ['healthcheck' => 'Comprobación de estado de plugins'],
        'privacy' => [
            'deadlines' => 'Comprobar plazos de solicitudes de interesados',
            'retention_scan' => 'Análisis de plazos de conservación',
        ],
        'recurrence' => ['generate' => 'Generar pedidos recurrentes'],
        'remote' => ['sync_sessions' => 'Importar sesiones de asistencia remota'],
        'scheduler' => ['watchdog' => 'Supervisión del planificador'],
        'security' => ['advisories_pull' => 'Obtener avisos de seguridad', 'integrity' => 'Comprobación de integridad del código fuente', 'evaluate' => 'Evaluar detección de ataques'],
        'tickets' => ['scan_sla_breaches' => 'Detectar incumplimientos de SLA'],
        'todoist' => ['sync' => 'Sincronización de Todoist'],
        'toggl' => ['import' => 'Importación de Toggl'],
        'updates' => ['check' => 'Comprobación de actualizaciones'],
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
