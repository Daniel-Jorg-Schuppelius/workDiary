<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vacation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Urlaubsantrag zur Korrektur zurückgeben (MVP-1554).
    'revision' => [
        'title' => 'Solicitar corrección',
        'action' => 'Solicitar corrección',
        'legend' => 'Indicación para la corrección',
        'note' => '¿Qué debe cambiarse?',
        'note_hint' => 'La persona solicitante ve la indicación, modifica la solicitud y la presenta de nuevo.',
        'requested' => 'Solicitud de vacaciones devuelta para corrección.',
        'resubmitted' => 'Solicitud de vacaciones modificada y presentada de nuevo.',
        'returned' => 'Esta solicitud se ha devuelto para corrección.',
        'resubmit_hint' => 'Al guardar, presenta de nuevo la solicitud.',
        'not_decidable' => 'La solicitud está en corrección en manos de la persona solicitante y solo puede decidirse tras presentarla de nuevo.',
    ],
];
