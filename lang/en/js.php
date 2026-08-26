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
];
