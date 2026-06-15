<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'status' => 'Copia de seguridad y restauración',
        'log_restore_test' => 'Registrar una prueba de restauración',
    ],

    'subtitle' => 'Estado de las copias externas por fuente, avisos de frescura y registro de las pruebas de restauración realizadas.',

    'section' => [
        'last_per_source' => 'Última copia por fuente',
        'restore_register' => 'Registro de pruebas de restauración',
        'restore_test' => 'Prueba de restauración',
        'retention' => 'Retención',
    ],

    'field' => [
        'source' => 'Fuente',
        'occurred_at' => 'Marca de tiempo',
        'age' => 'Antigüedad',
        'size' => 'Tamaño',
        'manifest_hash' => 'Hash del manifiesto',
        'state' => 'Estado',
        'tested_on' => 'Probado el',
        'result' => 'Resultado',
        'scope' => 'Alcance',
        'restored_size' => 'Restaurado',
        'restored_size_bytes' => 'Tamaño restaurado (bytes)',
        'duration' => 'Duración',
        'duration_minutes' => 'Duración (minutos)',
        'next_due' => 'Próximo vencimiento',
        'performed_by' => 'Realizado por',
        'notes' => 'Nota',
        'last_passed' => 'Última prueba correcta',
        'no_passed_test' => 'Aún no se ha registrado ninguna prueba de restauración correcta',
    ],

    'badge' => [
        'fresh' => 'al día',
        'overdue' => 'vencida',
    ],

    'value' => [
        'hours' => ':n h',
        'minutes' => ':n min',
        'days_ago' => 'hace :n días',
    ],

    'action' => [
        'log_restore_test' => 'Registrar una prueba de restauración',
        'save' => 'Guardar',
        'open_help' => 'Abrir el manual de copias de seguridad',
    ],

    'warn' => [
        'no_heartbeat_title' => 'No hay copia de seguridad registrada',
        'no_heartbeat_body' => 'Aún no se ha recibido ningún heartbeat de copia de seguridad. Compruebe que el script de copia externo se ejecuta y llama al endpoint de heartbeat con un token válido.',
        'overdue_title' => 'Copia de seguridad vencida',
        'overdue_body' => 'Al menos una fuente no ha notificado un heartbeat desde hace más de :hours horas. Revise la última copia.',
        'restore_overdue_title' => 'Prueba de restauración vencida',
        'restore_overdue_body' => 'No se ha registrado ninguna prueba de restauración correcta desde hace más de :days días. Realice una prueba de recuperación y regístrela aquí.',
    ],

    'hint' => [
        'freshness' => 'Una fuente se considera vencida si su heartbeat más reciente tiene más de :hours horas (configurable mediante BACKUP_HEARTBEAT_FRESHNESS_HOURS).',
        'register_manual' => 'Este es un registro trazable. La restauración real se realiza manualmente o mediante script fuera de WorkDiary — la ejecución automatizada de la restauración no forma parte de esta página de forma intencionada.',
        'retention' => 'Retención recomendada: 7 diarias, 4 semanales, 12 mensuales (regla 3-2-1). Al menos una copia fuera del sitio en otra ubicación.',
        'see_docs' => 'Los detalles sobre la estrategia, el heartbeat y la restauración paso a paso están en docs/backup-restore.md.',
    ],

    'empty' => [
        'no_heartbeat' => 'No hay copia de seguridad registrada',
        'no_heartbeat_hint' => 'En cuanto el script de copia externo envíe un heartbeat, aquí aparecerá la última copia por fuente.',
        'no_restore_tests' => 'Aún no hay pruebas de restauración registradas',
    ],

    'placeholder' => [
        'source' => 'p. ej. nightly, offsite, weekly-full',
        'scope' => 'p. ej. BD+almacenamiento, solo adjuntos',
        'notes' => 'Observaciones, condiciones, desviaciones …',
    ],

    'flash' => [
        'restore_test_logged' => 'Prueba de restauración registrada.',
    ],

    'generated_at' => 'A fecha de: :at',
];
