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
        'start' => 'Dictate',
        'stop' => 'Stop recording',
        'working' => 'Transcribing …',
    ],
    'field' => [
        'activity' => 'Task',
        'work_done' => 'Done',
        'defects' => 'Defects',
        'note' => 'Note',
    ],
    'error' => [
        'unavailable' => 'Speech recognition is not set up on this server.',
        'denied' => 'No access to the microphone — please allow it in the browser.',
        'unsupported' => 'This browser cannot record.',
        'audio_missing' => 'The recording did not arrive.',
        'no_speech' => 'No speech was recognised in the recording.',
    ],
];
