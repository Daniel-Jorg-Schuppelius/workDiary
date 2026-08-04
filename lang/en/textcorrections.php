<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : textcorrections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Dictionary',
        'subtitle' => 'Spelling corrections (wrong → right) applied automatically to generated position texts — the recorded time entries remain unchanged.',
    ],

    'notice' => 'Entries apply automatically when position texts are built for transfers and invoice drafts (whole word, letter case is carried over). The original time entry texts are never modified.',
    'search_placeholder' => 'Search (wrong/right) …',
    'legend' => 'Dictionary entry',
    'empty' => 'No dictionary entries yet',
    'delete_confirm' => 'Delete this dictionary entry? The correction will no longer be applied.',
    'wrong_placeholder' => 'e.g. servermaintenannce',
    'wrong_help' => 'Misspelled word or phrase — matched as a whole word only, case-insensitive.',
    'correct_placeholder' => 'e.g. server maintenance',
    'correct_help' => 'Correct spelling — it replaces the misspelling in all generated position texts.',

    'field' => [
        'wrong' => 'Wrong',
        'correct' => 'Right',
        'origin' => 'Origin',
        'origin_manual' => 'Manual',
        'origin_learned' => 'Learned',
        'usage' => 'Used',
        'active' => 'Active',
        'enabled_yes' => 'Yes',
        'enabled_no' => 'No',
    ],

    'action' => [
        'new' => 'Add entry',
        'edit' => 'Edit entry',
        'submit' => 'Save',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'delete' => 'Delete',
    ],

    'flash' => [
        'saved' => 'Dictionary entry created.',
        'updated' => 'Dictionary entry updated.',
        'deleted' => 'Dictionary entry deleted.',
        'activated' => 'Dictionary entry activated.',
        'deactivated' => 'Dictionary entry deactivated.',
        'learned' => 'Correction added to the dictionary.',
        'duplicate_updated' => 'The entry already existed and has been updated.',
        'invalid' => 'Wrong and right must not be identical.',
    ],

    'validation' => [
        'duplicate' => 'An entry for this misspelling already exists.',
    ],

    'learn' => [
        'title' => 'Remember correction?',
        'question' => 'Word corrections were detected in your edit. Add them to the dictionary so they are applied automatically in the future?',
        'confirm' => 'Remember',
        'dismiss' => 'Don\'t remember',
    ],
];
