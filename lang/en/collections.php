<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : collections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sammlungen (MVP-809, Feature 155).
return [
    'title' => [
        'index' => 'Collections',
        'tree' => 'Collection tree',
    ],
    'subtitle' => 'Organize notes, idea maps, knowledge articles, documents and learning content together — one item may sit in several collections.',
    'action' => [
        'show_archived' => 'Show archived',
        'hide_archived' => 'Hide archived',
        'create' => 'Create collection',
        'create_child' => 'Create sub-collection',
        'edit' => 'Edit collection',
        'save' => 'Save',
        'archive' => 'Archive',
        'restore' => 'Restore',
        'remove_item' => 'Remove from collection',
        'add_to_collection' => 'Add to collection',
        'add' => 'Add',
    ],
    'empty' => [
        'tree' => 'No collection created yet.',
        'selection' => 'No collection selected.',
        'items' => 'This collection is empty — or only holds items you are not allowed to see.',
    ],
    'help' => [
        'intro' => 'A collection organizes content, it grants no access: everyone only sees what they may see anyway.',
        'add_from_detail' => 'Add items via “Add to collection” on their detail page.',
        'parent' => 'At most :max levels deep.',
        'visibility' => 'Only the creator sees a private collection.',
        'create_first' => 'First create a collection under “Collections”.',
        'multiple_membership' => 'An item may sit in several collections without a copy.',
    ],
    'visibility' => [
        'organization' => 'Organization',
        'private' => 'Private',
    ],
    'badge' => [
        'archived' => 'Archived',
        'already_in' => 'already included',
    ],
    'field' => [
        'type' => 'Type',
        'title' => 'Title',
        'added_by' => 'Added',
        'actions' => 'Actions',
        'description' => 'Description',
        'parent' => 'Parent collection',
        'no_parent' => '— top level —',
        'visibility' => 'Visibility',
        'subject' => 'Reference',
        'customer' => 'Customer',
        'collection' => 'Collection',
    ],
    'flash' => [
        'created' => 'Collection created.',
        'updated' => 'Collection saved.',
        'archived' => 'Collection archived.',
        'restored' => 'Collection restored.',
        'item_added' => 'Added to “:collection”.',
        'item_removed' => 'Removed from the collection.',
        'items_added' => '{0} No items added.|{1} One item added to “:collection”.|[2,*] :count items added to “:collection”.',
    ],
    'errors' => [
        'too_deep' => 'Collections can be nested at most :max levels deep.',
        'cycle' => 'A collection cannot sit below itself or one of its sub-collections.',
        'type_not_allowed' => 'This kind of content cannot be added to a collection.',
        'item_not_found' => 'The item does not exist or is not visible to you.',
        'parent_invalid' => 'The chosen parent collection does not exist (any more).',
    ],
    'type' => [
        'note' => 'Note',
        'idea_map' => 'Idea map',
        'knowledge_article' => 'Knowledge article',
        'document' => 'Document',
        'learning_course' => 'Course',
        'learning_path' => 'Learning path',
    ],
    'references' => [
        'title' => 'References',
        'outgoing' => 'Refers to',
        'backlinks' => 'Mentioned in',
        'action' => [
            'create' => 'Add reference',
            'remove' => 'Remove reference',
        ],
        'field' => [
            'target' => 'Target',
            'search' => 'Search content …',
        ],
        'kind' => [
            'linked' => 'linked',
            'converted' => 'converted',
            'mentioned' => 'mentioned',
        ],
        'empty' => 'No references yet – neither from here nor to here.',
        'empty_picker' => 'No other content you can refer to.',
        'help' => 'A reference connects two items without granting access: only people who may open the other side see it.',
        'confirm_remove' => 'Remove this reference? Both items remain.',
        'flash' => [
            'added' => 'Reference to “:title” added.',
            'removed' => 'Reference removed.',
        ],
        'errors' => [
            'self' => 'An item cannot refer to itself.',
            'not_removable' => 'This reference is maintained by its module, not by this list.',
        ],
    ],
    'hub' => [
        'title' => 'Knowledge',
        'subtitle' => 'Notes, idea maps, knowledge articles, documents and learning content in one place — organised by collections and tags.',
        'search' => 'Search titles …',
        'all_types' => 'All kinds',
        'all_customers' => 'All customers',
        'all_contents' => 'All content',
        'view_list' => 'List',
        'view_tiles' => 'Tiles',
        'manage' => 'Manage collections',
        'tag_filter' => 'Tag filter',
        'selected' => ':n items selected',
        'select_all' => 'Select all',
        'select_item' => 'Select “:title”',
        'updated' => 'Changed',
        'empty' => 'No content found.',
        'empty_hint' => 'Relax the filters or choose another collection.',
        'add_hits' => 'Add to collection',
    ],
    'import' => [
        'untitled' => 'Untitled',
        'source' => 'Source',
        'target' => 'Import as',
        'root_title' => 'Collection name',
        'root_title_hint' => 'Empty: name of the folder or notebook. A collection with the same name is reused.',
        'rules' => 'Read-only and on demand only: items imported before stay unchanged, nothing is written back. At most :max new items per run — a further run imports the rest.',
        'origin' => 'Imported from :source on :date',
        'action' => [
            'start' => 'Start import',
        ],
        'obsidian' => [
            'title' => 'Import from Obsidian',
            'action' => 'Import Obsidian',
            'intro' => 'Reads an Obsidian folder through an existing folder connection of the document intake (Nextcloud, OneDrive, Dropbox, Google Drive). Sub-folders become collections, YAML tags and #tags come along, [[wikilinks]] become references.',
            'none' => 'No active folder connection. First set up a connection under Administration › Cloud document intake that reaches the folder with the Obsidian vault.',
            'connection' => 'Folder connection',
            'vault_path' => 'Vault path',
            'vault_path_hint' => 'Relative to the connection’s root folder; empty = the whole root folder. .obsidian/ and .trash/ are left out.',
        ],
        'onenote' => [
            'title' => 'Import from OneNote',
            'action' => 'Import OneNote',
            'intro' => 'Imports a notebook through the read-only OneNote connection: section groups and sections become collections, each page a note or an article. The page content is imported as text.',
            'none' => 'No notebooks found.',
            'error' => 'OneNote is not reachable right now — please check the connection in the Microsoft 365 panel.',
            'notebook' => 'Notebook',
        ],
        'flash' => [
            'done' => 'Imported: :created new, :skipped already present, :collections collections created, :links references added.',
            'limited' => 'Limit of :max new items reached — a further run imports the rest.',
            'failed' => 'The import failed. Items imported so far are kept; a further run continues.',
            'source_unavailable' => 'The source is not (or no longer) available.',
            'notebook_invalid' => 'The selected notebook is not (or no longer) available.',
        ],
    ],
];
