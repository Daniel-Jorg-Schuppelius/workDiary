<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : knowledge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Knowledge base',
        'links' => 'Problem history',
        'linked' => 'Linked articles',
        'suggestions' => 'Suggestions',
    ],

    'subtitle' => 'Known issues, solution steps and internal notes from day-to-day operations.',

    'field' => [
        'title' => 'Title',
        'category' => 'Category',
        'tags' => 'Tags',
        'status' => 'Status',
        'problem' => 'Problem description',
        'solution' => 'Solution steps',
        'helpful' => 'Rating',
        'creator' => 'Created by',
        'published_at' => 'Published at',
        'updated_at' => 'Last updated',
    ],

    'action' => [
        'create' => 'Create article',
        'create_from_subject' => 'Create article from this',
        'edit' => 'Edit',
        'save' => 'Save',
        'show' => 'View',
        'publish' => 'Publish',
        'archive' => 'Archive',
        'delete' => 'Delete',
        'link' => 'Link',
        'unlink' => 'Remove link',
        'back' => 'Back',
    ],

    'filter' => [
        'all' => 'All',
        'search' => 'Search',
        'search_placeholder' => 'Search title, problem or solution',
        'sort' => 'Sort',
        'sort_newest' => 'Newest first',
        'sort_helpful' => 'Most helpful first',
    ],

    'feedback' => [
        'title' => 'Was this article helpful?',
        'helpful' => 'It helped',
        'not_helpful' => 'It did not help',
        'already_voted' => 'You already voted — voting again changes your vote.',
    ],

    'link_kind' => [
        'diary' => 'Job',
        'asset' => 'Asset',
        'customer' => 'Customer',
        'protocol' => 'Protocol',
    ],

    'hint' => [
        'category' => 'e.g. printer, network, heating …',
        'tags' => 'Comma separated, e.g. firmware, model-x',
        'problem' => 'Which symptom/problem occurs?',
        'solution' => 'Which steps lead to the solution?',
    ],

    'flash' => [
        'created' => 'Article created.',
        'updated' => 'Article updated.',
        'published' => 'Article published.',
        'archived' => 'Article archived.',
        'deleted' => 'Article deleted.',
        'feedback_saved' => 'Thanks for your rating.',
        'linked' => 'Article linked.',
        'unlinked' => 'Link removed.',
    ],

    'empty' => 'No knowledge articles yet.',
    'empty_title' => 'No articles found',
    'empty_filtered' => 'No articles match the current filters.',
    'empty_links' => 'No links yet.',
    'empty_context' => 'No linked articles and no matching suggestions.',
    'confirm_archive' => 'Really archive this article? It will disappear from search and suggestions.',
    'confirm_delete' => 'Really delete this article?',
    'confirm_unlink' => 'Really remove this link?',
];
