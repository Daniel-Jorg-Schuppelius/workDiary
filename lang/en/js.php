<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : js.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/*
 * Strings exposed to JavaScript via window.__translations.
 * Keys here are also accessible from JS via window.__('js.key') after
 * the JS i18n bridge has run. Keep this list lean.
 */

return [
    'dialog' => [
        'check_input' => 'Please check your input.',
        'save_failed' => 'Dialog could not be saved.',
        'load_failed' => 'Dialog could not be loaded.',
        'loading' => 'Loading…',
        'open_in_new_tab' => 'Open page in a new tab',
        'switch_to_new' => 'Switch to the new mode',
        'switch_to_legacy' => 'Switch to the legacy mode',
    ],
    'schedule' => [
        'move_failed' => 'Move failed.',
        'suggest_failed' => 'Could not load suggestions.',
    ],
    // Dienstplan-Oberflaeche (MVP-797): war zuvor fest verdrahtetes Deutsch.
    'schedule_ui' => [
        'shift_edit' => 'Edit shift',
        'shift_create' => 'Create shift',
        'shift_delete_confirm' => 'Really delete this shift?',
        'shift_type_edit' => 'Edit shift type',
        'shift_type_create' => 'Create shift type',
        'shift_type_delete_confirm' => 'Really delete this shift type?',
        'save' => 'Save',
        'delete' => 'Delete',
        'close' => 'Close',
        'save_failed' => 'Saving failed.',
        'delete_failed' => 'Deleting failed.',
        'publish_failed' => 'Publishing failed.',
        'confirm_failed' => 'Confirmation failed.',
        'suggestions_title' => 'Staffing suggestions',
        'col_employee' => 'Employee',
        'col_score' => 'Score',
        'col_reason' => 'Reason',
        'type_active_yes' => 'yes',
        'type_active_no' => 'no',
    ],
    'bulk' => [
        'select_one' => 'Please select at least one entry first.',
    ],
    'design' => [
        'inheritance' => '":base" · :inherited/:total inherited, :own overridden',
    ],
    // Chat-Oberflaeche (MVP-798): angepinnte Nachrichten werden per JSON geladen.
    'chat' => [
        'pinned_failed' => 'Pinned messages could not be loaded.',
        'pinned_empty' => 'No pinned messages.',
    ],
    'kanban' => [
        'invalid_move' => 'This status change is not part of the order workflow.',
        'not_allowed' => 'You are not authorised to perform this order action.',
        'handover_via_order' => 'Handover requires a signed protocol and is performed directly in the order.',
        'no_targets' => 'There is currently no permitted move for this card.',
    ],
    'entry_bar' => [
        'options_failed' => 'Tasks/orders could not be loaded.',
    ],
    'http' => [
        'session_expired' => 'Your session has expired — the page will reload.',
    ],
    // KI-Tagvorschläge im Tag-Picker (Feature 143, MVP-711)
    'ai' => [
        'tags_no_text' => 'Please enter some content first — the AI suggests tags from the text.',
        'tags_none' => 'No existing tag matches the text.',
        'tags_failed' => 'AI tag suggestion not possible: :message',
        'tags_loading' => 'AI is looking for matching tags …',
    ],
    // Tastenkürzel-Übersicht (Feature 037, MVP-721): Labels der Registry resources/js/shortcuts.js
    'shortcuts' => [
        'help' => 'Open contextual help for the current page',
        'title' => 'Keyboard shortcuts',
        'scope' => [
            'global' => 'Global',
            'navigation' => 'Navigation',
            'search' => 'Search',
        ],
        'search' => 'Open global search',
        'shortcuts' => 'Show this overview',
        'escape' => 'Close dialog or search',
        'search_move' => 'Move through search results',
        'search_open' => 'Open result',
        'go_diary' => 'Go to diary',
        'go_customers' => 'Go to customers',
        'go_projects' => 'Go to projects',
        'new_entry' => 'New entry',
        'then' => 'then',
    ],
    'quiz' => [
        'progress' => ':answered of :total answered',
    ],
    // NFC-Scan (MVP-903).
    'nfc' => [
        'hold' => 'Hold the tag to the device …',
        'empty' => 'The tag contains no link.',
        'failed' => 'NFC not possible.',
        'written' => 'Tag written.',
    ],
];
