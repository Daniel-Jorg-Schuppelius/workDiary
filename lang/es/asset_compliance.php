<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset_compliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfwesen: Bearbeiten, Deaktivieren, Storno, Roh-Messwerte (MVP-1575).
return [
    'action' => [
        'deactivate' => 'Desactivar',
        'activate' => 'Activar',
    ],
    'state' => [
        'inactive' => 'desactivado',
    ],
    'profile' => [
        'edit' => 'Editar perfil de inspección',
        'updated' => 'Perfil de inspección guardado.',
        'activated' => 'Perfil de inspección activado.',
        'deactivated' => 'Perfil de inspección desactivado.',
        'deactivate_confirm' => '¿Desactivar el perfil de inspección? Dejará de ofrecerse para nuevas asignaciones; las obligaciones y los informes existentes se conservan.',
        'inactive_title' => 'Perfiles de organización desactivados',
        'interval_hint' => 'Se aplica a todas las obligaciones sin intervalo propio, a partir de la próxima inspección.',
    ],
    'requirement' => [
        'edit' => 'Editar requisito',
        'edit_hint' => 'Los límites modificados se aplican a las nuevas inspecciones; los informes anteriores conservan sus límites de entonces.',
        'updated' => 'Requisito guardado.',
        'activated' => 'Requisito activado.',
        'deactivated' => 'Requisito desactivado.',
    ],
    'assignment' => [
        'edit' => 'Editar obligación de inspección',
        'updated' => 'Obligación de inspección guardada.',
        'activated' => 'Obligación de inspección reanudada.',
        'deactivated' => 'Obligación de inspección desactivada.',
        'deactivate_confirm' => '¿Desactivar la obligación de inspección? Las citas abiertas se cancelan y termina el bloqueo por vencimiento de esta obligación; los informes se conservan.',
        'interval_override' => 'Intervalo para este activo (meses)',
        'interval_override_hint' => 'Vacío = intervalo del perfil de inspección (:months meses).',
        'next_due_on' => 'Próximo vencimiento',
        'next_due_hint' => 'Vacío = recalcular desde la última inspección si cambia el intervalo.',
        'inactive_title' => 'Obligaciones de inspección desactivadas',
        'show_active' => 'Mostrar activas',
        'show_inactive' => 'Desactivadas (:count)',
        'block_released' => 'Obligación de inspección modificada o desactivada.',
    ],
    'schedule' => [
        'cancel_title' => 'Cancelar cita de inspección',
        'cancel_action' => 'Cancelar',
        'cancel_hint' => 'La cita permanece en la lista con su motivo (filtro «Cancelada»). La obligación de inspección sigue vigente.',
        'cancelled' => 'Cita de inspección cancelada.',
        'none_for_status' => 'No hay citas de inspección con este estado.',
        'cancelled_by_deactivation' => 'Obligación de inspección desactivada.',
        'cancelled_by_decommission' => 'Activo dado de baja.',
        'error' => [
            'not_cancellable' => 'Esta cita de inspección ya se ha realizado o cancelado.',
        ],
    ],
    'measurement' => [
        'title' => 'Mediciones brutas',
        'hint' => 'Serie de mediciones libre sin comprobación de límites; las filas sin valor no se guardan.',
        'label' => 'Punto de medición',
        'value' => 'Valor medido',
    ],
    'protocol' => [
        'title' => 'Informes de inspección (últimos 20)',
        'performed_at' => 'Realizada',
        'values' => 'Resultados y mediciones',
        'out_of_limit' => 'fuera de límite',
        'empty' => 'Aún no se ha documentado ninguna inspección.',
    ],
];
