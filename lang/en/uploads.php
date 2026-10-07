<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : uploads.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Datei-Uploads mit Zweck (MVP-1074): Hinweise, Fortschritt und Fehler je Datei.
 */

return [
    'label' => 'Files',
    'hint' => 'Allowed: :formats · up to :size per file · at most :count files, :total in total.',
    'progress' => 'Uploading … :percent %',
    'error' => [
        'type' => ':name: file type not allowed.',
        'too_large' => ':name is larger than :size.',
        'count' => 'Please select at most :count files at once.',
        'total' => 'The files are too large in total (at most :size per submission).',
        'failed' => 'The upload failed. Your entries have not been saved — please try again.',
    ],
];
