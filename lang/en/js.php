<?php
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
    ],
    'entry_bar' => [
        'options_failed' => 'Tasks/orders could not be loaded.',
    ],
];
