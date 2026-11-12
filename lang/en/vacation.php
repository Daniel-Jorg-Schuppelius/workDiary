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
        'title' => 'Request correction',
        'action' => 'Request correction',
        'legend' => 'Note on the correction',
        'note' => 'What should be changed?',
        'note_hint' => 'The applicant sees the note, changes the request and submits it again.',
        'requested' => 'Leave request returned for correction.',
        'resubmitted' => 'Leave request changed and submitted again.',
        'returned' => 'This request was returned for correction.',
        'resubmit_hint' => 'Saving submits the request again.',
        'not_decidable' => 'The request is with the applicant for correction and can only be decided after it has been submitted again.',
    ],
];
