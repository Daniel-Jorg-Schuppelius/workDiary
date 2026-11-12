<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'error' => [
        'stamp_in_future' => 'La marca de tiempo está en el futuro.',
        'stamp_too_old' => 'La marca de tiempo tiene más de :days días y ya no se acepta.',
        'day_locked' => 'El día está cerrado o el mes aprobado: solicite una corrección de tiempo.',
        'stamp_overlaps' => 'Ya existe un fichaje para ese momento.',
        'forbidden' => 'No tiene permiso para realizar esta acción.',
        'module_inactive' => 'Esta área no está habilitada para su organización.',
    ],
    // Konflikte statt Ablehnung: der Nutzer entscheidet (MVP-1593/1594).
    'conflict' => [
        'missing_fields' => 'Faltan datos obligatorios: :fields.',
        'day_locked' => 'El :date se cerró o el mes se aprobó entretanto: la reserva no se ha adoptado. Cambie la fecha o solicite una corrección de tiempo.',
        'lock' => [
            'day' => 'Día cerrado',
            'month' => 'Mes aprobado',
        ],
    ],
];
