<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recruiting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Recruiting-Ergänzungen (MVP-924/925).
return [
    'suitability' => [
        'title' => 'Suitability matrix',
        'subtitle' => 'Required competencies for “:title” and assessment of the applications — an aid for selection, not an automatic decision.',
        'requirements' => 'Required competencies',
        'no_requirements' => 'No requirements defined yet.',
        'no_competencies' => 'No competencies exist yet (competency catalogue of the learning platform).',
        'competency' => 'Competency',
        'level' => 'Level',
        'add' => 'Add requirement',
        'remove' => 'Remove',
        'confirm_remove' => 'Remove this required competency?',
        'required' => 'Required :level',
        'matrix' => 'Applications',
        'candidate' => 'Applicant',
        'gaps' => 'Gaps',
        'score' => 'Fulfilment',
        'no_applications' => 'No applications for this position.',
        'rating_title' => 'Competency assessment',
        'note' => 'Reasoning (internal)',
        'save' => 'Save',
        'flash' => [
            'requirement' => 'Requirement saved.',
            'requirement_removed' => 'Requirement removed.',
            'rated' => 'Assessment saved.',
        ],
    ],
    'offer' => [
        'title' => 'Offer interview slots',
        'slot' => 'Slot :n',
        'mode' => 'Interview type',
        'duration' => 'Duration (minutes)',
        'valid_days' => 'Valid (days)',
        'interviewer' => 'Interviewer',
        'send' => 'Send invitation',
        'ics_title' => 'Job interview',
        'public_title' => 'Choose an interview slot',
        'public_intro' => 'Please choose a slot (:minutes minutes, :mode).',
        'choose' => 'Offered slots',
        'confirm' => 'Confirm this slot',
        'confirmed_title' => 'Slot confirmed',
        'confirmed_text' => 'We look forward to meeting you on :when at :org. A confirmation with calendar entry is on its way.',
        'flash' => [
            'sent' => 'Invitation with :count slots sent.',
        ],
        'error' => [
            'no_email' => 'This application has no valid email address.',
            'no_slots' => 'Please enter at least one future slot.',
            'unavailable' => 'This slot is no longer available.',
        ],
        'mail' => [
            'subject' => 'Your interview: please choose a slot (:title)',
            'body' => "Dear :name,\n\nwe would like to meet you. Please choose one of the following slots by :until:\n:slots\n\nChoose a slot: :url",
            'confirmed_subject' => 'Confirmation of your interview',
            'confirmed_body' => "Dear :name,\n\nyour interview takes place on :when (:mode). The appointment is attached as a calendar entry.",
        ],
    ],
];
