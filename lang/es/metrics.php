<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metrics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Métricas operativas',
    ],

    'subtitle' => 'Indicadores técnicos y uso agregado de funciones de esta instalación.',

    'privacy_notice' => 'Todas las métricas se recopilan y almacenan exclusivamente en local. No se envía nada al exterior; el uso de funciones se cuenta solo como agregado diario por organización — sin referencia personal y sin contenido de negocio.',

    'section' => [
        'queue' => 'Cola',
        'backups' => 'Heartbeats de copia de seguridad',
        'plugin_errors' => 'Errores de plugins (7 días)',
        'storage' => 'Almacenamiento',
        'active_users' => 'Usuarios activos (30 días)',
        'module_counts' => 'Registros por módulo principal',
        'feature_usage' => 'Uso de funciones (30 días)',
        'transparency' => 'Transparencia de métricas',
    ],

    'field' => [
        'version' => 'Versión',
        'queue_pending' => 'Trabajos pendientes',
        'queue_failed' => 'Trabajos fallidos',
        'attachments' => 'Adjuntos',
        'document_versions' => 'Versiones de documentos',
        'feature' => 'Función',
        'usage_total' => 'Cantidad',
        'last_used_on' => 'Último uso',
        'counter_description' => 'Qué se cuenta',
    ],

    'transparency' => [
        'status_enabled' => 'Contadores de uso activos',
        'status_disabled' => 'Contadores de uso desactivados',
        'settings_link' => 'Interruptor «telemetry.enabled» en la configuración',
        'intro' => 'Esta instalación recopila exclusivamente los siguientes contadores de uso — agregados por organización y día, sin referencia personal y sin contenido de negocio. No existe otra telemetría.',
        'storage' => 'Ubicación de almacenamiento: base de datos local de esta instalación (tabla feature_usage_counters). No se envía nada a sistemas externos.',
        'retention' => 'Conservación: los agregados diarios permanecen en local; el análisis anterior cubre los últimos 30 días. Con el interruptor desactivado no se cuenta nada — los agregados existentes se conservan.',
    ],

    'counter' => [
        'communications' => [
            'created' => 'Notas de comunicación creadas (solo cantidad)',
        ],
        'documents' => [
            'created' => 'Documentos creados (solo cantidad)',
        ],
        'forms' => [
            'submitted' => 'Formularios cumplimentados (solo cantidad)',
        ],
        'knowledge' => [
            'created' => 'Artículos de conocimiento creados (solo cantidad)',
        ],
        'timeExports' => [
            'built' => 'Exportaciones de tiempos/nómina generadas (solo cantidad)',
        ],
    ],

    'module' => [
        'diary_entries' => 'Encargos (diario)',
        'protocols' => 'Protocolos',
        'documents' => 'Documentos',
        'form_submissions' => 'Formularios (cumplimentados)',
        'knowledge_articles' => 'Artículos de conocimiento',
        'communication_notes' => 'Notas de comunicación',
    ],

    'empty' => [
        'queue' => 'No hay tablas de cola disponibles (driver sync).',
        'backups' => 'Aún no se han recibido heartbeats de copia de seguridad.',
        'plugin_errors' => 'Sin errores de plugins en los últimos 7 días.',
        'active_users' => 'No hay datos de inicio de sesión disponibles.',
        'feature_usage' => 'Aún no se ha registrado uso de funciones.',
    ],

    'hint' => [
        'storage_db_metadata' => 'Cantidad y tamaño según los metadatos de la base de datos (sin escaneo del sistema de archivos — la ocupación del disco se muestra en la página de diagnóstico).',
        'active_users' => 'Usuarios distintos con inicio de sesión en los últimos 30 días (fuente: registro de auditoría).',
        'feature_usage_window' => 'Agregado por organización y día durante los últimos 30 días. Los datos permanecen en local.',
    ],

    'generated_at' => 'Generado: :at',
];
