<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : operations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Tareas operativas',
        'subtitle' => 'Actualizaciones, copias de seguridad, caducidades y averías — priorizadas y trazables.',
        'widget' => 'Tareas operativas abiertas',
    ],
    'type' => [
        'backup_overdue' => 'Copia de seguridad atrasada',
        'backup_failed' => 'Copia de seguridad fallida',
        'restore_test_overdue' => 'Prueba de restauración atrasada',
        'update_available' => 'Actualización disponible',
        'update_security' => 'Actualización de seguridad',
        'license_expiring' => 'Caducidad de licencia',
        'license_limit_near' => 'Límite de usuarios casi alcanzado',
        'credential_expiring' => 'Caducidad de credencial/token',
        'connection_failing' => 'Fallo de conexión',
        'component_eol' => 'Componente sin soporte',
        'plugin_disabled' => 'Plugin desactivado',
        'scheduler_overdue' => 'Tarea programada atrasada',
        'queue_failed_jobs' => 'Trabajos en segundo plano fallidos',
        'queue_worker_down' => 'Worker de cola inactivo',
        'maintenance_scheduled' => 'Ventana de mantenimiento',
        'config_missing' => 'Configuración faltante',
        'support_grant_open' => 'Autorización de soporte abierta',
        'problem_report_open' => 'Informe de problema abierto',
        'cloud_intake_reauth' => 'Entrada en la nube: se requiere iniciar sesión',
        'cloud_intake_quarantined' => 'Entrada en la nube: importaciones rechazadas',
    ],
    'severity' => [
        'info' => 'Aviso',
        'warning' => 'Advertencia',
        'critical' => 'Crítico',
    ],
    'status' => [
        'open' => 'Abierta',
        'snoozed' => 'Pospuesta',
        'delegated' => 'Delegada',
        'ignored' => 'Ignorada',
        'done' => 'Completada',
        'resolved' => 'Resuelta por sí sola',
    ],
    'field' => [
        'task' => 'Tarea',
        'severity' => 'Gravedad',
        'status' => 'Estado',
        'first_seen' => 'Detectada el',
        'last_seen' => 'Confirmada el',
        'assignee' => 'Responsable',
        'actions' => 'Acciones',
        'note' => 'Justificación',
        'snooze_until' => 'Posponer hasta',
        'system_wide' => 'A nivel de instalación',
    ],
    'action' => [
        'done' => 'Completar',
        'snooze' => 'Posponer',
        'delegate' => 'Delegar',
        'ignore' => 'Ignorar',
        'reopen' => 'Reabrir',
        'open_link' => 'Ir a la causa',
    ],
    'task' => [
        'backup_overdue' => 'La última copia de seguridad tiene :hours horas (umbral :threshold h).',
        'backup_failed' => 'Falló la comprobación de la copia de seguridad: :reason',
        'backup_target_failed' => 'Copia de seguridad en la nube fallida: :reason',
        'backup_target_verify_failed' => 'Verificación de la copia en la nube fallida: :reason',
        'restore_test_overdue' => 'La última prueba de restauración fue hace :days días (umbral :threshold días).',
        'restore_test_missing' => 'Nunca se ha registrado una prueba de restauración.',
        'update_available' => 'Actualización disponible para :component: :installed → :available.',
        'update_security' => 'Actualización de seguridad para :component: :installed → :available (:classification).',
        'license_expiring' => 'La licencia caduca el :date (:days días restantes).',
        'license_limit_near' => ':org: :current de :max puestos licenciados en uso — amplíe la licencia a tiempo.',
        'credential_expiring' => ':kind «:name» caduca el :date.',
        'connection_failing' => 'Conexión «:name» (:kind) con fallos: :error',
        'component_eol' => ':component :version no tiene soporte desde el :date.',
        'plugin_disabled' => 'El plugin «:plugin» se desactivó automáticamente tras :failures fallos.',
        'scheduler_overdue' => 'La tarea programada «:job» está atrasada (vencimiento :due).',
        'queue_failed_jobs' => ':count trabajos en segundo plano fallidos (último :last) — revisar failed_jobs, queue:retry.',
        'queue_worker_down' => 'El worker de cola no se ha reportado desde hace :minutes minutos — revisar servicio/cron.',
        'maintenance_scheduled' => 'Ventana de mantenimiento :from – :to::scope',
        'support_grant_open' => 'Autorización de soporte para :grantee activa hasta :until.',
        'problem_report_open' => 'El informe :reference de :name espera su tramitación.',
        'problem_report_summary' => ':count informe(s) de problemas abiertos esperan tramitación.',
        'cloud_intake_reauth' => 'La entrada de documentos en la nube :provider (“:folder”) necesita volver a conectarse (:status).',
        'cloud_intake_quarantined' => ':count archivo(s) de la entrada de documentos en la nube fueron rechazados (último motivo: :reason).',
        'support_grant_summary' => ':count autorización(es) de soporte activa(s): revisar y revocar si es necesario.',
    ],
    'filter' => [
        'active' => 'Tareas activas',
        'all_severities' => 'Todas las gravedades',
        'all_types' => 'Todos los tipos',
    ],
    'empty' => [
        'title' => 'Sin tareas operativas',
        'message' => 'Nada que hacer ahora mismo: todas las tareas operativas están completadas o resueltas por sí solas.',
    ],
    'hint' => [
        'auto_disabled_after' => 'Desactivado automáticamente tras :failures intentos fallidos.',
        'no_contact_since' => 'Sin contacto desde el :date.',
    ],
    'flash' => [
        'done' => 'Tarea marcada como completada.',
        'snoozed' => 'Tarea pospuesta hasta :date.',
        'delegated' => 'Tarea delegada.',
        'ignored' => 'Tarea ignorada.',
        'reopened' => 'Tarea reabierta.',
    ],
    'widget' => [
        'open' => 'Tareas abiertas',
        'empty' => 'No hay tareas operativas abiertas.',
        'all' => 'Mostrar todas las tareas operativas',
    ],
];
