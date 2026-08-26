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
    // Workspaces — switchable focus views (Feature 082).
    'focus' => [
        'admin' => [
            'title' => 'Workspaces',
            'subtitle' => 'Choose which workspaces your organization offers in the switcher, rename them, and pick a default.',
            'hint' => 'Only a suggestion: the default workspace is never forced on anyone — everyone can switch any time. Hiding changes no permissions.',
            'list_heading' => 'Offered workspaces',
            'configured_at' => 'Last set: :date',
            'mandatory' => 'always available',
            'is_default' => 'Default',
            'rename' => 'Display name',
            'offered' => 'offered',
            'set_default' => 'Default',
            'saved' => 'Workspaces saved.',
        ],
        'switcher' => 'Switch workspace',
        'eyebrow' => 'Workspace',
        'all' => 'Show everything',
        'active' => 'Active',
        'reveal' => 'Show all',
        'reveal_off' => 'Focus only',
        'dialog' => [
            'eyebrow' => 'Focus your view',
            'title' => 'What are you working on?',
            'subtitle' => 'Pick a workspace — the navigation then shows only the relevant areas. Nothing is deleted or locked; you can switch any time.',
            'footnote' => 'Hidden areas stay reachable via global search and “Show all”.',
        ],
        'flash' => [
            'unknown' => 'Unknown workspace.',
            'switched' => 'Workspace “:name” active.',
        ],
        'personal' => [
            'title' => 'Personal workspace',
            'description' => 'Your own selection of menu entries.',
            'heading' => 'Personal workspaces',
            'manage' => 'Manage personal workspaces',
        ],
    ],
    'workspace' => [
        'title' => 'Personal workspaces',
        'subtitle' => 'Build your own workspaces from menu entries. They appear in the switcher next to the predefined views and only hide things — they never change permissions.',
        'create' => 'New workspace',
        'edit' => 'Edit workspace',
        'empty' => 'No personal workspace yet',
        'name' => 'Name',
        'icon' => 'Icon',
        'sort' => 'Order',
        'items' => 'Menu entries',
        'available' => 'Available',
        'selected' => 'Selected',
        'items_hint' => 'Only entries you may see anyway are offered. Set the order by dragging or with the buttons.',
        'add' => 'Add',
        'remove' => 'Remove',
        'move_up' => 'Move up',
        'move_down' => 'Move down',
        'drag_hint' => 'Drag to sort — or use the “Move up”/“Move down” buttons.',
        'count' => ':count menu entries',
        'active' => 'Active',
        'delete_title' => 'Delete workspace',
        'delete_confirm' => 'The workspace will be removed. Menu entries and permissions stay untouched.',
        'error' => [
            'no_items' => 'Select at least one menu entry.',
            'unknown_item' => 'At least one menu entry is not available to you.',
        ],
        'flash' => [
            'created' => 'Workspace created.',
            'updated' => 'Workspace saved.',
            'deleted' => 'Workspace removed.',
        ],
    ],
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
        'subtitle' => 'Turn on what should appear in your menu — turn off what you personally do not need. Applies only to you, on all devices.',
        'cosmetic_hint' => 'Hiding does not change permissions: search, bookmarks and direct links keep working. “All functions” brings everything back.',
        'sidebar_heading' => 'Side navigation',
        'hide_section' => 'hide entire section',
        'hide_group' => 'hide subgroup',
        'create_heading' => 'Quick create (“New …”)',
        'create_hint' => 'Hidden groups no longer appear in the sidebar “New …” menu.',
        'checkbox_hint' => 'On = shown in the menu.',
        'saved' => 'Menu customization saved.',
        'unhidden' => 'Entry is visible again.',
    ],
    'functions' => [
        'focus_banner' => 'Active workspace “:name”. Hidden areas are marked below — they stay reachable here.',
        'in_focus_hidden' => 'Hidden by workspace',
        'show_all' => 'Show everything',
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
