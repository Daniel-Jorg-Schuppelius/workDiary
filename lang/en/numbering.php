<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : numbering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vergabe von Kunden- und Lieferantennummern durch ein Buchhaltungsprogramm (Phase 141, MVP-1130–1134),
// Seite „Nummernkreise“ (Phase 144, MVP-1147–1149).
return [
    'source' => [
        'local' => 'workDiary',
        'inactive' => ':program (inactive – workDiary assigns)',
        'column' => 'Assigned by',
        'page_hint' => 'If an accounting program assigns customer or supplier numbers, a new record receives its number when it is transferred. Existing numbers stay unchanged. Invoice, credit note and cancellation numbers are assigned by the program of the billing route, or by Lexoffice if the switch is set there. If a program assigns the numbers, the format is locked.',
        'billing' => ':program (billing route)',
        'deviation' => ':program for one customer|:program for :count customers',
    ],
    'field' => [
        'locked_hint' => ':program assigns the number on transfer.',
        'row' => ':field (:scope)',
    ],
    'separators' => [
        'prefix' => 'Separator after prefix, all ranges',
        'year' => 'Separator after year, all ranges',
        'apply' => 'Apply to all ranges',
        'hint' => 'Applies both separators to every range that workDiary assigns and saves the page. An empty field means no separator. Numbers already assigned stay unchanged.',
    ],
    'preview' => [
        'program' => 'Set by :program',
        'locked_hint' => 'Format and number are set by :program.',
        'next_remote' => 'Next in :program: :number',
        'last_adopted' => 'Latest from :program: :number',
        'pending' => 'One contact is waiting for its number.|:count contacts are waiting for their number.',
    ],
    'issue' => [
        'no_issuer' => ':program cannot transfer :scope. Choose on the “Number ranges” page who assigns the number.',
        'quote_pending' => ':program assigns the quote number when the quote is sent; until then it carries a draft number.',
        'draft_number' => 'This document carries a draft number, and :program cannot take it over. Set who assigns the number on the “Number ranges” page.',
        'transfer_failed' => 'Transfer to :program failed; the document remains a draft. :error',
        'issue_first' => '“:document” still carries a draft number. Issue the document first – the accounting program assigns the number then.',
        'invoice_pending' => ':program assigns the number on issuing; WorkDiary transfers the document at that moment. Until then it carries a draft number.',
    ],
    'flash' => [
        'saved' => 'One number range saved.|:count number ranges saved.',
        'unchanged' => 'No changes.',
        'transfer_pending' => 'Document “:document” created. On issuing, WorkDiary transfers it to :program, which also assigns its number.',
        'issued' => 'Number :number taken over from :program.',
        'pending' => 'The number from :program follows with the next transfer: :reason',
        'conflict' => 'Number :number from :program is already assigned to :holder. You will find the case in the matching inbox.',
    ],
    'conflict' => [
        'title' => 'Number :number from :program is already assigned to :holder',
    ],
    'error' => [
        'taken_remote' => 'Number :number is already assigned to “:name” in :program.',
        'taken_local' => 'Number :number is already assigned to :holder. Please change the number there first.',
        'no_number' => ':program did not return a number.',
    ],
];
