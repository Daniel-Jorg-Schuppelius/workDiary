<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ideas.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Idea maps',
    ],
    'subtitle' => 'Private and shared idea maps — visible only to the owner and explicitly shared people.',
    'empty' => 'No idea maps yet.',
    'privacy_hint' => 'New maps are private: visible only to you until you explicitly share them with people or teams.',
    'confirm_delete' => 'Move map to trash?',

    'action' => [
        'create' => 'Create map',
        'edit' => 'Edit map',
        'archive' => 'Archive',
        'unarchive' => 'Unarchive',
        'restore' => 'Restore',
    ],

    'col' => [
        'title' => 'Title',
        'description' => 'Description',
        'owner' => 'Owner',
        'visibility' => 'Visibility',
        'nodes' => 'Nodes',
        'updated' => 'Updated',
        'actions' => 'Actions',
    ],

    'filter' => [
        'active' => 'Active',
        'archived' => 'Archived',
        'trashed' => 'Trash',
    ],

    'visibility' => [
        'private' => 'Private',
        'shared' => 'Shared',
    ],

    'share_role' => [
        'viewer' => 'View',
        'editor' => 'Edit',
    ],

    'color' => [
        'default' => 'Neutral',
        'primary' => 'Blue',
        'success' => 'Green',
        'warning' => 'Yellow',
        'error' => 'Red',
        'info' => 'Teal',
    ],

    'node_status' => [
        'open' => 'Open',
        'in_review' => 'In review',
        'decided' => 'Decided',
        'rejected' => 'Rejected',
        'done' => 'Done',
    ],

    'import' => [
        'action' => 'Import',
        'title' => 'Import idea map',
        'submit' => 'Import',
        'file' => 'File',
        'hint' => 'FreeMind/Freeplane (.mm) or OPML. Creates a new, private map.',
        'done' => 'Map imported.',
        'default_title' => 'Imported map',
        'error' => [
            'invalid' => 'The file is not valid XML.',
            'unsupported' => 'Unsupported format (only FreeMind .mm and OPML).',
            'empty' => 'The file contains no nodes.',
            'too_deep' => 'The structure is nested too deeply.',
            'too_large' => 'The map has too many nodes.',
        ],
    ],

    'legend' => [
        'context' => 'Context (optional)',
        'map' => 'Map',
    ],

    'outline' => [
        'title' => 'Outline',
        'empty' => 'This map has no nodes yet.',
    ],

    'flash' => [
        'created' => 'Map created.',
        'updated' => 'Map saved.',
        'archived' => 'Map archived.',
        'unarchived' => 'Map unarchived.',
        'deleted' => 'Map moved to trash.',
        'restored' => 'Map restored.',
        'owner_invalid' => 'Invalid new owner.',
        'ownership_transferred' => 'Ownership transferred.',
        'share_granted' => 'Share granted.',
        'share_revoked' => 'Share revoked.',
        'share_invalid' => 'Invalid share selection (exactly one person or one team).',
    ],

    'share' => [
        'title' => 'Shares',
        'none' => 'This map is private — no shares.',
        'user' => 'Person',
        'team' => 'Team',
        'role' => 'Role',
        'add' => 'Share',
        'revoke' => 'Revoke share',
        'hint' => 'Exactly one person OR one team per share. Team membership is checked on access.',
    ],

    'notification' => [
        'shared' => ':actor shared an idea map with you.',
    ],

    'export' => [
        'generated_at' => 'Generated at',
        'footer_note' => 'Export of the outline view — canvas positions are included in the JSON export.',
    ],

    'context' => [
        'customer' => 'Customer',
        'project' => 'Project',
    ],

    'convert' => [
        'done' => 'Converted:',
        'already' => 'Already converted:',
        'error' => [
            'module_disabled' => 'The target module is not enabled.',
            'target_not_allowed' => 'This target is not allowed.',
        ],
    ],

    'editor' => [
        'outline' => 'Outline',
        'canvas' => 'Canvas',
        'saving' => 'Saving …',
        'undo_delete' => 'Undo delete',
        'keys_hint' => 'Enter: new node · Tab: indent · Alt+↑/↓: move · F2: rename',
        'conflict_title' => 'Concurrent change detected — your copy was stale.',
        'conflict_take_server' => 'Take server version',
        'conflict_retry_mine' => 'Re-apply my change',
        'new_node' => 'New idea',
        'convert_task' => 'To task',
        'convert_project' => 'To project',
        'convert_knowledge' => 'To knowledge article',
        'confirm_delete_node' => 'Move node and its children to trash?',
        'add_child' => 'Add child node',
        'rename' => 'Rename',
        'details' => 'Details',
        'move_up' => 'Move up',
        'move_down' => 'Move down',
        'indent' => 'Indent',
        'outdent' => 'Outdent',
        'delete' => 'Delete',
        'expand' => 'Expand branch',
        'collapse' => 'Collapse branch',
        'zoom_in' => 'Zoom in',
        'zoom_out' => 'Zoom out',
        'zoom_reset' => 'Reset zoom to 100%',
        'fit' => 'Fit view',
        'arrange' => 'Arrange',
        'arrange_hint' => 'Automatically arrange all nodes as a tree',
        'canvas_large' => 'Large workspace',
        'canvas_small' => 'Compact workspace',
        'canvas_keys_hint' => 'Tab: child node · Enter: sibling · double-click canvas: new idea · drag onto a node: re-attach',
        'canvas_a11y_hint' => 'Accessible editing in the outline view.',
        'export_svg' => 'Export as SVG image',
        'export_png' => 'Export as PNG image',
        'history' => 'History',
        'history_empty' => 'No changes yet.',
        'presence_suffix' => 'currently editing',
        'note' => 'Note',
        'color' => 'Color',
        'status' => 'Status',
        'status_none' => '— no status',
    ],

    'error' => [
        'conflict' => 'The node was changed in the meantime — please review the current state.',
        'cycle' => 'A node cannot be moved below one of its own descendants.',
        'root_immovable' => 'The root node cannot be moved or deleted.',
        'foreign_node' => 'The node does not belong to this map.',
    ],
];
