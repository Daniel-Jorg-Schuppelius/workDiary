<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Feature scope',
    ],
    'nav' => [
        'customize' => 'Customize menu',
        'functions' => 'All functions',
    ],
    'page' => [
        'subtitle' => 'Define the visible feature scope of your organization: presets for a quick start, or toggle modules individually.',
        'no_data_loss' => 'Deactivating only hides modules and locks their pages — no data is deleted. Everything is back when you reactivate.',
    ],
    'presets' => [
        'heading' => 'Presets',
        'hint' => 'A preset is a shortcut: it switches the module list below in one step. You can fine-tune afterwards.',
        'apply' => 'Apply preset “:preset”',
        'all_modules' => 'All licensed modules',
        'module_count' => '{1} :count additional module|[2,*] :count additional modules',
    ],
    'recommendation' => [
        'heading' => 'Recommendation from the branch profile',
        'hint' => 'The installed branch profile “:profile” recommends the following modules.',
        'apply' => 'Apply recommendation',
    ],
    'modules' => [
        'heading' => 'Set modules individually',
        'configured_at' => 'Last configured: :date',
        'not_licensed_hint' => 'Not included in the current plan — can be added via license management.',
    ],
    'flash' => [
        'saved' => 'Feature scope saved (:disabled deactivated, :enabled activated). No data was deleted.',
        'no_recommendation' => 'There is no branch-profile recommendation for this organization.',
    ],
    'customize' => [
        'subtitle' => 'Hide menu areas you personally do not need. The setting applies only to you, on all devices.',
        'cosmetic_hint' => 'Hiding does not change permissions: search, bookmarks and direct links keep working. “All functions” brings everything back.',
        'sidebar_heading' => 'Side navigation',
        'hide_section' => 'hide entire section',
        'hide_group' => 'hide subgroup',
        'create_heading' => 'Quick create (“New …”)',
        'create_hint' => 'Hidden groups no longer appear in the sidebar “New …” menu.',
        'checkbox_hint' => 'Checked = hidden.',
        'saved' => 'Menu customization saved.',
        'unhidden' => 'Entry is visible again.',
    ],
    'functions' => [
        'subtitle' => 'Overview of all areas and their state — including everything hidden, deactivated or not licensed.',
        'state' => [
            'hidden_section' => 'Section hidden',
            'org_disabled' => 'Deactivated by the organization',
            'hidden_by_me' => 'Hidden by me',
        ],
        'action' => [
            'unhide' => 'Show',
            'enable_module' => 'Open feature scope',
        ],
        'upsell_hint' => 'This module is not included in the current plan.',
    ],
];
