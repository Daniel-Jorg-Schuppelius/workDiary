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
        'start' => 'Dictar',
        'stop' => 'Detener grabación',
        'working' => 'Transcribiendo …',
    ],
    'field' => [
        'activity' => 'Tarea',
        'work_done' => 'Realizado',
        'defects' => 'Defectos',
        'note' => 'Nota',
    ],
    'error' => [
        'unavailable' => 'El reconocimiento de voz no está configurado en este servidor.',
        'denied' => 'Sin acceso al micrófono — permítalo en el navegador.',
        'unsupported' => 'Este navegador no puede grabar.',
        'audio_missing' => 'La grabación no ha llegado.',
        'no_speech' => 'No se reconoció voz en la grabación.',
    ],
];
