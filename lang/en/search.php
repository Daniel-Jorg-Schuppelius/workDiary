<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : search.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Search',
    'subtitle' => 'Find activities, customers and objects — what was done, when and for which customer?',
    'placeholder' => 'e.g. smtp exchange, "smtp relay", -test',

    'group' => [
        'activities' => 'Activities',
    ],

    'source' => [
        'time_entry' => 'Time entry',
        'diary_entry' => 'Job',
        'timesheet' => 'Timesheet',
        'service_ticket' => 'Ticket',
        'protocol' => 'Protocol',
        'open_issue' => 'Open item',
        'communication_note' => 'Communication note',
        'knowledge_article' => 'Knowledge article',
        'remote_session' => 'Remote session (unassigned)',
    ],

    'field' => [
        'query' => 'Search term',
        'type' => 'Source',
        'all_types' => 'All sources',
        'person' => 'Person',
        'all_persons' => 'All people',
        'customer' => 'Customer',
        'all_customers' => 'All customers',
        'foreign_customer' => 'End customer',
        'all_foreign_customers' => 'All end customers',
        'sort' => 'Sort order',
        'sort_relevance' => 'Best matches first',
        'sort_date' => 'Newest first',
        'similar' => 'Similar spellings',
    ],

    'filter' => [
        'project' => 'Project: :name',
        'remove' => 'Remove filter',
    ],

    'notice' => [
        'corrections' => '“:word” does not occur — also searched for: :candidates.',
        'synonyms' => 'Also searched: :list',
        'ignored' => 'Not considered: :words',
    ],

    'aggregate' => [
        'title' => 'Customers & end customers',
        'without_customer' => 'no customer',
        'hits' => ':count hit|:count hits',
    ],

    'types' => [
        'title' => 'Sources',
    ],

    'hits' => [
        'title' => 'Activities',
        'open' => 'Open',
    ],

    'column' => [
        'date' => 'Date',
        'activity' => 'Activity',
        'customer' => 'Customer › End customer / Project',
        'person' => 'Person',
        'duration' => 'Duration',
    ],

    'empty' => [
        'start' => 'What are you looking for?',
        'start_hint' => 'Keywords are enough, e.g. “smtp exchange”. All words must occur — in the entry, the project or the customer.',
        'none' => 'No results.',
        'none_hint' => 'Try fewer words or switch on “Similar spellings”.',
    ],

    'entities' => [
        'title' => 'Master data & objects',
        'more' => 'All results in this group →',
        'back' => '← Back to all results',
    ],

    'box' => [
        'title' => 'Search activities',
        'label' => 'Search term',
        'placeholder' => 'Keywords, e.g. smtp exchange',
        'placeholder_customer' => 'What was done for this customer or its end customers?',
        'placeholder_foreign_customer' => 'What was done for this end customer?',
        'hint_customer' => 'Searches time entries, jobs, timesheets, tickets, protocols and notes of the customer and all of its end customers. Without a search term the latest activities are shown.',
        'hint_foreign_customer' => 'Searches all activities for this end customer. Without a search term the latest are shown.',
        'submit' => 'Search',
        'project_action' => 'Search activities',
    ],

    'open' => [
        'range_set' => 'Period set to :date so the entry appears in the list.',
    ],

    'palette' => [
        'placeholder' => 'Search activities, customers, projects, objects …',
    ],

    'ai' => [
        'action' => 'AI answer',
        'source_hint' => 'Search “:query” · :count hits',
        'customer_alias' => 'Customer :letter',
        'no_hits' => 'There are no results to summarise.',
    ],

    'synonyms' => [
        'title' => 'Search synonyms',
        'subtitle' => 'Terms with the same meaning: searching for one also finds the others.',
        'notice' => 'Example: if “smtp, mailrelay, sendeconnector” form a group, a search for “smtp” also finds entries that only mention “Sendeconnector”. Applies to the whole organisation.',
        'legend' => 'Synonym group',
        'terms_help' => 'One term per line (or separated by commas), at least two, at most 20. Multi-word terms such as “send connector” are allowed.',
        'empty' => 'No synonym groups yet.',
        'delete_confirm' => 'Delete this synonym group? The search will no longer find the terms for each other.',
        'field' => [
            'terms' => 'Terms',
            'creator' => 'Created by',
            'active' => 'Active',
            'enabled_yes' => 'Yes',
            'enabled_no' => 'No',
        ],
        'action' => [
            'new' => 'Add group',
            'edit' => 'Edit group',
            'submit' => 'Save',
            'activate' => 'Activate',
            'deactivate' => 'Deactivate',
            'delete' => 'Delete',
            'preset_it' => 'Import IT template',
        ],
        'flash' => [
            'saved' => 'Synonym group created.',
            'updated' => 'Synonym group updated.',
            'deleted' => 'Synonym group deleted.',
            'activated' => 'Synonym group activated.',
            'deactivated' => 'Synonym group deactivated.',
            'preset_imported' => '{0} All template groups already exist.|{1} :count group imported from the template.|[2,*] :count groups imported from the template.',
        ],
        'validation' => [
            'min_terms' => 'A group needs at least two different terms.',
            'max_terms' => 'At most :max terms per group.',
            'term_length' => 'A term may be at most :max characters long.',
        ],
    ],
];
