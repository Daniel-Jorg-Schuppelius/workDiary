<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : updates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => ['section' => 'Actualizaciones disponibles'],
    'field' => [
        'mode' => 'Modo de comprobación',
        'last_checked' => 'Última comprobación',
        'component' => 'Componente',
        'versions' => 'Instalada → Disponible',
        'classification' => 'Clasificación',
        'requirements' => 'Preparación',
        'incompatible' => 'Incompatible con esta versión de la aplicación',
        'changelog' => 'Registro de cambios',
    ],
    'classification' => [
        'normal' => 'Rutina',
        'recommended' => 'Recomendada',
        'security' => 'Seguridad',
        'critical' => 'Crítica',
    ],
    'requires' => [
        'backup' => 'Se requiere copia de seguridad',
        'maintenance_window' => 'Ventana de mantenimiento recomendada',
        'migrations' => 'Migraciones de base de datos',
    ],
    'action' => [
        'check_now' => 'Comprobar ahora',
        'import' => 'Importación sin conexión',
        'snooze' => 'Posponer',
        'acknowledge' => 'Silenciar',
    ],
    'empty' => 'No se conocen actualizaciones pendientes.',
    'flash' => [
        'checked' => 'Comprobación finalizada — :count actualización(es) pendiente(s).',
        'imported' => 'Documento importado — :count actualización(es) pendiente(s).',
        'snoozed' => 'Aviso de actualización pospuesto.',
        'acknowledged' => 'Aviso silenciado (sigue visible aquí).',
    ],
];
