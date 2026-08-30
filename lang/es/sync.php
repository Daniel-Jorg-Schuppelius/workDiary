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
    ],
];
