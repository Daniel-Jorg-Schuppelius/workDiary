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
        'start' => 'Diktieren',
        'stop' => 'Aufnahme beenden',
        'working' => 'Wird verschriftet …',
    ],
    'field' => [
        'activity' => 'Tätigkeit',
        'work_done' => 'Erledigt',
        'defects' => 'Mängel',
        'note' => 'Notiz',
    ],
    'error' => [
        'unavailable' => 'Die Spracherkennung ist auf diesem Server nicht eingerichtet.',
        'denied' => 'Kein Zugriff auf das Mikrofon — bitte im Browser erlauben.',
        'unsupported' => 'Dieser Browser kann nicht aufnehmen.',
        'audio_missing' => 'Die Aufnahme ist nicht angekommen.',
        'no_speech' => 'In der Aufnahme wurde keine Sprache erkannt.',
    ],
];
