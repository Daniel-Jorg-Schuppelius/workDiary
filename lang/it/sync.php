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
        'stamp_in_future' => 'La marca temporale è nel futuro.',
        'stamp_too_old' => 'La marca temporale risale a più di :days giorni e non viene più accettata.',
        'day_locked' => 'La giornata è chiusa o il mese approvato: richieda una correzione dei tempi.',
        'stamp_overlaps' => 'Esiste già una timbratura per questo momento.',
    ],
];
