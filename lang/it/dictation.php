<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dictation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Sprachdiktat (MVP-1060).
return [
    'action' => [
        'start' => 'Detta',
        'stop' => 'Termina registrazione',
        'working' => 'Trascrizione …',
    ],
    'field' => [
        'activity' => 'Attività',
        'work_done' => 'Eseguito',
        'defects' => 'Difetti',
        'note' => 'Nota',
    ],
    'error' => [
        'unavailable' => 'Il riconoscimento vocale non è configurato su questo server.',
        'denied' => 'Nessun accesso al microfono — lo consenta nel browser.',
        'unsupported' => 'Questo browser non può registrare.',
        'audio_missing' => 'La registrazione non è arrivata.',
        'no_speech' => 'Nella registrazione non è stato riconosciuto alcun parlato.',
    ],
];
