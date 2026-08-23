<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'action' => [
        'push' => 'Transfer to accounting',
    ],

    'flash' => [
        'pushed' => 'Customer transferred to accounting (ID :id).',
        'failed' => 'Transfer failed: :msg',
        'no_plugin' => 'No accounting system is active.',
    ],

    'error' => [
        'accounting_leads' => 'Accounting owns the master data — nothing is transferred (setting “master data authority”).',
        'no_syncer' => 'The :plugin plugin does not transfer contacts.',
    ],

    'authority' => [
        'workdiary' => 'workDiary leads',
        'accounting' => 'Accounting leads',
    ],

    // Lokale Buchhaltung (Feature 125, MVP-671): Einrichtung, Buchungshoheit,
    // Geschäftsjahre und Preflight.
    'ledger' => [
        'title' => 'Local accounting',
        'menu' => 'Accounting',
        'setup_menu' => 'Setup',
        'subtitle' => 'Posting authority, fiscal years and setup preflight for local bookkeeping.',
        'open_ended' => 'ongoing',
        'section' => [
            'profile' => 'Accounting profile',
            'preflight' => 'Preflight',
            'fiscal_years' => 'Fiscal years',
            'sovereignty' => 'Posting authority',
        ],
        'field' => [
            'profit_determination' => 'Profit determination',
            'base_currency' => 'Base currency',
            'fiscal_year_start_month' => 'Fiscal year starts in',
            'starts_on' => 'Posting start (effective date)',
            'note' => 'Note',
            'fiscal_year_starts_on' => 'Start of the fiscal year',
            'fiscal_year_label' => 'Label',
            'sovereignty' => 'New posting authority',
            'external_provider' => 'Leading system',
            'valid_from' => 'Valid from',
            'reason' => 'Reason',
            'datev_account' => 'DATEV account',
            'euer_category' => 'Cash-basis line',
            'euer_category_none' => '— unassigned —',
            'deductible_percent' => 'Deductible share (%)',
            'description' => 'Description',
            'post_now' => 'Post immediately',
            'reversal_reason' => 'Reason',
            'reversal_booked_on' => 'Posting date of the counter-entry',
        ],
        'hint' => [
            'profit_determination' => 'Changes the reporting (cash basis or double entry), not the posting and audit rules.',
            'base_currency' => 'The first release keeps exactly one currency; deviating documents are shown with a reason instead of being converted.',
            'starts_on' => 'Local postings start on this day. Earlier documents stay history and are not posted retroactively.',
            'fiscal_year_starts_on' => 'Twelve monthly periods are created up to the day before the next year.',
            'fiscal_year_label' => 'Leave empty for “2026” or “2026/2027” with a deviating fiscal year.',
            'sovereignty' => 'Who kept the ledger for which period stays traceable — even after a switch.',
            'sovereignty_switch' => 'Moving the data remains the accounting migration; this only reassigns who leads from the effective date.',
            'external_provider' => 'External authority only: name of the leading system (e.g. lexoffice).',
            'datev_account' => 'Export only; local posting does not depend on it.',
            'euer_category' => 'Determines which line of the cash-basis form the account appears in. Without an assignment it shows up among the unresolved cases.',
            'deductible_percent' => 'Applies to the cash-basis report only — the journal always carries the full amount (e.g. 70 % for business meals).',
            'normal_balance' => 'Prefilled from the account type, overridable per account.',
            'post_now' => 'Once posted, the entry can only be corrected through a counter-entry.',
            'reversal_booked_on' => 'Leave empty for the original day, as long as its period is still open.',
        ],
        'action' => [
            'activate' => 'Activate local accounting',
            'add_fiscal_year' => 'Add fiscal year',
            'switch' => 'Switch posting authority',
            'switch_submit' => 'Reassign authority',
            'add_account' => 'Add account',
            'edit_account' => 'Edit account',
            'deactivate' => 'Deactivate',
            'add_entry' => 'New entry',
            'post' => 'Post',
            'reverse' => 'Reverse',
            'reverse_submit' => 'Create counter-entry',
        ],
        'column' => [
            'fiscal_year' => 'Fiscal year',
            'range' => 'Period',
            'periods' => 'Periods',
            'status' => 'Status',
            'from' => 'From',
            'to' => 'To',
            'holder' => 'Authority',
            'reason' => 'Reason',
            'number' => 'Account',
            'name' => 'Name',
            'type' => 'Account type',
            'normal_balance' => 'Balance side',
            'flags' => 'Flags',
            'journal_no' => 'No.',
            'booked_on' => 'Posting date',
            'document_on' => 'Document date',
            'memo' => 'Description',
            'accounts' => 'Accounts',
            'amount' => 'Amount',
            'debit' => 'Debit',
            'credit' => 'Credit',
            'account' => 'Account',
            'document_reference' => 'Document',
            'posted_by' => 'Posted by',
            'source' => 'Source',
        ],
        'empty' => [
            'accounts' => 'No account created yet.',
            'entries' => 'No entry in this period yet.',
            'fiscal_years' => 'No fiscal year created yet.',
            'sections' => 'No change of authority recorded yet.',
        ],
        'flash' => [
            'saved' => 'Accounting profile saved.',
            'activated' => 'Local accounting activated.',
            'fiscal_year_created' => 'Fiscal year :year created with periods.',
            'sovereignty_switched' => 'Posting authority switched.',
            'account_saved' => 'Account saved.',
            'account_deactivated' => 'Account deactivated.',
            'imported' => 'Account import: :imported new, :updated updated, :errors errors.',
            'entry_saved' => 'Entry saved.',
            'entry_posted' => 'Entry posted.',
            'entry_reversed' => 'Counter-entry created.',
        ],
        'error' => [
            'sovereignty' => 'On :date the ledger is kept by :holder — local postings are not allowed for that day.',
            'fiscal_year_overlap' => 'The range overlaps with fiscal year :year.',
            'start_locked' => 'The posting start cannot be changed after activation.',
            'provider_required' => 'External posting authority requires naming the leading system.',
            'sovereignty_unchanged' => 'This posting authority already applies on that date.',
            'later_section_exists' => 'A later authority section already starts on :date.',
            'period_closed' => 'The period starting :period no longer accepts entries.',
            'no_period' => 'There is no accounting period for :date.',
            'entry_frozen' => 'The entry is posted — correction only through a counter-entry.',
            'needs_two_lines' => 'An entry needs at least two lines.',
            'unknown_account' => 'A line refers to an unknown account.',
            'inactive_account' => 'Account :account is deactivated.',
            'foreign_currency_line' => 'All lines must be in :currency.',
            'negative_amount' => 'Amounts are positive; the direction comes from debit or credit.',
            'both_sides' => 'A line carries either debit or credit, never both.',
            'unbalanced' => 'Debit (:debit) and credit (:credit) do not match.',
            'reverse_not_posted' => 'Only a posted entry can be reversed.',
            'reversal_reason_required' => 'A reversal requires a reason.',
            'account_in_use' => 'This account has been used — it can only be deactivated.',
            'entry_without_organization' => 'The entry has no organization — please inform your system administrator.',
            'account_number_taken' => 'This account number already exists.',
        ],
        'preflight' => [
            'not_configured' => 'Profile not saved yet — the preflight runs from the first save.',
            'blocked_hint' => 'Activation stays locked while any check is red.',
            'profile_missing' => 'No accounting profile has been saved yet.',
            'starts_on_missing' => 'No posting start has been set.',
            'starts_on_ok' => 'Posting start: :date.',
            'fiscal_year_missing' => 'There is no fiscal year covering the posting start.',
            'periods_missing' => 'Fiscal year :year has no periods.',
            'fiscal_year_ok' => 'Fiscal year :year with :count periods.',
            'migration_active' => 'An accounting migration is running (:status) — authority is not unambiguous meanwhile.',
            'migration_none' => 'No accounting migration in progress.',
            'handed_over' => 'DATEV batch :batch already covers the period up to :to.',
            'handed_over_none' => 'No exported booking batch overlaps the period.',
            'sovereignty_conflict' => 'From :date :holder already leads — the period would be claimed twice.',
            'sovereignty_ok' => 'No competing authority section.',
            'foreign_currency' => ':count documents from the effective date are not in :currency; they stay visible in the posting inbox.',
            'base_currency_ok' => 'All documents from the effective date are in :currency.',
            'billing_external' => 'Invoices are issued by :program — documents will come from there.',
            'billing_local' => 'workDiary issues the outgoing invoices itself.',
            'master_data_external' => 'Master data is led by accounting; customers and suppliers are not overwritten from here.',
            'master_data_local' => 'workDiary leads the master data.',
            'key' => [
                'profile' => 'Profile',
                'starts_on' => 'Effective date',
                'fiscal_year' => 'Fiscal year',
                'migration_run' => 'Migration',
                'handed_over' => 'Handovers',
                'sovereignty' => 'Authority',
                'base_currency' => 'Currency',
                'billing_mode' => 'Invoicing',
                'master_data' => 'Master data',
            ],
        ],
        'reversal_memo' => 'Reversal of entry #:no',
        'opening_memo' => 'Opening balance',
        'reverse_hint' => 'The reversal creates a real counter-entry. The original entry stays unchanged.',
        'accounts' => [
            'title' => 'Chart of accounts',
            'menu' => 'Chart of accounts',
            'subtitle' => 'Accounts, balance side and DATEV mapping of local accounting.',
        ],
        'journal' => [
            'title' => 'Journal',
            'menu' => 'Journal',
            'subtitle' => 'Posted and prepared entries in the selected period.',
        ],
        'entry' => [
            'title' => 'Entry',
            'head' => 'Entry header',
            'lines' => 'Entry lines',
            'total' => 'Total',
            'is_reversal_of' => 'This entry reverses entry #:no.',
            'reversed_by' => 'Reversed by entry #:no — :reason',
        ],
        'filter' => [
            'only_active' => 'active only',
            'all_types' => 'All account types',
            'all_states' => 'All states',
        ],
        'flag' => [
            'open_item' => 'Open items',
            'bank' => 'Bank',
            'cash' => 'Cash',
            'clearing' => 'Clearing',
            'inactive' => 'Inactive',
        ],
        'confirm' => [
            'deactivate' => 'Really deactivate this account? Existing entries are kept.',
        ],
        'import' => [
            'line_invalid' => 'Line :line skipped (number, name or account type missing).',
        ],
    ],

    // Buchungs-Inbox und Mappingregeln (Feature 125, MVP-673).
    'inbox' => [
        'title' => 'Posting inbox',
        'menu' => 'Posting inbox',
        'subtitle' => 'Documents, expenses and cash entries of the period with their posting status.',
        'empty' => 'No open items in the period.',
        'four_eyes_active' => 'Four-eyes principle active: whoever prepared a proposal does not post it themselves.',
        'state' => [
            'blocked' => 'Blocked',
            'open' => 'Unposted',
            'ready' => 'Ready',
            'posted' => 'Posted',
        ],
        'column' => [
            'kind' => 'Source',
            'document' => 'Document',
            'booked_on' => 'Date',
            'proposal' => 'Proposal',
        ],
        'filter' => [
            'all_kinds' => 'All sources',
            'include_posted' => 'show posted',
        ],
        'action' => [
            'prepare' => 'Accept proposal',
            'prepare_and_post' => 'Accept and post',
            'batch_prepare' => 'Accept all',
            'batch_post' => 'Accept and post all',
        ],
        'confirm' => [
            'batch' => 'Accept all unblocked items of the period as drafts?',
            'batch_post' => 'Accept AND post all unblocked items? Posted entries can only be corrected by counter-entries.',
        ],
        'flash' => [
            'prepared' => 'Proposal accepted.',
            'batch' => 'Batch: :prepared accepted, :posted posted, :failed open.',
        ],
        'error' => [
            'four_eyes' => 'Four-eyes principle: you prepared this entry — someone else has to post it.',
        ],
        'blocker' => [
            'missing_rule' => 'No posting rule for :role:criteria.',
            'handed_over' => 'The document is already part of an exported booking batch.',
            'no_tax_breakdown' => 'The document has no tax breakdown.',
            'no_amount' => 'The document has no amount.',
            'no_lines' => 'The proposal has no entry lines.',
            'sovereignty' => 'For this period the organization does not keep a local ledger.',
            'foreign_currency' => 'The document is in :currency, accounting is kept in :base — there is no verifiable conversion yet.',
            'unsupported_target' => 'There is no posting path for this payment target yet.',
        ],
        'memo' => [
            'sales_invoice' => 'Invoice :number · :customer',
            'incoming_invoice' => 'Incoming invoice :number · :seller',
            'expense' => 'Expense :description · :user',
            'cash_entry' => 'Cash :register · :purpose',
            'payment' => 'Payment (:kind) · :target',
        ],
        'reversal_reason' => [
            'unmatched' => 'Payment allocation removed — counter-entry.',
        ],
    ],
    'rules' => [
        'title' => 'Posting rules',
        'menu' => 'Posting rules',
        'subtitle' => 'Mapping of source and role to an account — versioned and effective-dated.',
        'empty' => 'No posting rule created yet.',
        'fallback' => 'Fallback rule (all cases)',
        'no_tax_code' => '— no tax code —',
        'column' => [
            'role' => 'Role',
            'match' => 'Criteria',
            'validity' => 'Validity',
            'priority' => 'Priority',
        ],
        'field' => [
            'tax_code' => 'Tax code',
            'match_key' => 'Criterion',
            'match_value' => 'Value',
        ],
        'hint' => [
            'role' => 'What the account stands for in the entry — revenue, receivable, input tax …',
            'tax_code' => 'Optional; maps the frozen tax result of the document to an account.',
            'match' => 'Leave empty for the fallback rule. Examples: tax_rate = 19.00, expense_category_id = 5.',
            'priority' => 'Higher wins; on a tie the more specific rule.',
        ],
        'action' => [
            'add' => 'Add rule',
            'edit' => 'Edit rule',
        ],
        'confirm' => [
            'deactivate' => 'Deactivate rule? Existing entries keep their rule version.',
        ],
        'flash' => [
            'saved' => 'Posting rule saved.',
            'versioned' => 'New rule version created from the effective date.',
            'deactivated' => 'Posting rule deactivated.',
        ],
    ],

    // Offene Posten (Feature 125, MVP-674).
    'open_items' => [
        'title' => 'Open items',
        'menu' => 'Open items',
        'subtitle' => 'Receivables and payables from posted entries, with aging.',
        'empty' => 'No open items.',
        'overdue_days' => ':days days overdue',
        'settle_hint' => 'Open: :open. Payments come from the payment reconciliation — here only discount, retention or write-off.',
        'column' => [
            'counterparty' => 'Counterparty',
            'due_date' => 'Due',
            'original' => 'Original',
            'open' => 'Open',
            'kind' => 'Kind',
        ],
        'bucket' => [
            'not_due' => 'Not due',
            'd30' => '1–30 days',
            'd60' => '31–60 days',
            'd90' => '61–90 days',
            'd90plus' => 'over 90 days',
        ],
        'action' => [
            'settle' => 'Settle',
            'show_entry' => 'Show entry',
        ],
        'flash' => [
            'settled' => 'Settlement recorded.',
        ],
    ],

    // Wiederkehrende Vorgänge (Feature 125, MVP-675).
    'recurring' => [
        'title' => 'Recurring items',
        'menu' => 'Recurring',
        'subtitle' => 'Document expectations, posting templates and invoice schedules at a glance.',
        'principle' => 'A document expectation creates neither a document nor an entry — only the original fulfils it. Posting templates create drafts only.',
        'invoice_schedules_hint' => 'Invoice schedules stay with the billing plan; shown here for context only.',
        'preview' => 'Next due dates: :dates',
        'no_account' => '— no account —',
        'section' => [
            'open_runs' => 'Open items',
            'templates' => 'Templates',
            'invoice_schedules' => 'Invoice schedules',
        ],
        'column' => [
            'template' => 'Template',
            'period' => 'Period',
            'expected' => 'Expected',
            'name' => 'Name',
            'kind' => 'Kind',
            'interval' => 'Interval',
            'next_due' => 'Next due',
            'responsible' => 'Responsible',
        ],
        'field' => [
            'due_day' => 'Due day',
            'starts_on' => 'Start',
            'ends_on' => 'End',
        ],
        'hint' => [
            'kind' => 'A document expectation waits for an original; a posting template creates a draft.',
            'due_day' => '1–28, so every month has that day.',
            'accounts' => 'Posting templates only — together with the expected amount.',
        ],
        'action' => [
            'add' => 'Add template',
            'edit' => 'Edit template',
            'run' => 'Run now',
            'pause' => 'Pause',
            'resume' => 'Resume',
            'end' => 'End',
            'open_schedules' => 'Open billing plans',
        ],
        'confirm' => [
            'end' => 'End the template? Items already created remain.',
        ],
        'empty' => [
            'runs' => 'No open items.',
            'templates' => 'No template created yet.',
            'schedules' => 'No active billing plan.',
        ],
        'flash' => [
            'saved' => 'Template saved.',
            'versioned' => 'Template saved as a new version.',
            'paused' => 'Template paused.',
            'resumed' => 'Template resumed.',
            'ended' => 'Template ended.',
            'ran' => 'Run executed.',
        ],
        'error' => [
            'already_closed' => 'This item is already closed.',
            'template_incomplete' => 'A posting template needs debit account, credit account and amount.',
        ],
        'blocker' => [
            'no_lines' => 'The template has no entry lines.',
        ],
        'notification' => [
            'title' => 'Recurring item overdue: :name',
            'message' => 'Due on :due — status: :status.',
        ],
    ],

    // Finanzberichte (Feature 125, MVP-676).
    'reports' => [
        'title' => 'Financial reports',
        'menu' => 'Financial reports',
        'subtitle' => 'Reports of local accounting for the selected period.',
        'period' => 'Period :from – :to',
        'as_of' => 'As of :date',
        'empty' => 'No data in the period.',
        'vat_preview_hint' => 'Auditable preview — the MVP does not file a VAT return with ELSTER.',
        'euer_preview_hint' => 'Preview based on receipts and payments (§ 11 EStG), grouped by the lines of the German cash-basis form — not the form itself.',
        'euer_manual_hint' => 'to be entered manually',
        'pnl_hint' => 'Result by account groups — not an audited profit and loss statement.',
        'column' => [
            'euer_category' => 'Cash-basis line',
            'gross' => 'Amount',
            'deductible' => 'Deductible',
            'not_deductible' => 'Not deductible',
            'opening' => 'Opening balance',
            'closing' => 'Closing balance',
            'balance' => 'Balance',
            'direction' => 'Direction',
            'payable' => 'Payable',
            'result' => 'Result',
            'section' => 'Section',
        ],
        'section' => [
            'income' => 'Income',
            'expense' => 'Expenses',
            'balances' => 'Bank and cash accounts',
        ],
        'kpi' => [
            'cash' => 'Bank & cash',
            'receivable' => 'Receivables',
            'payable' => 'Payables',
            'forecast' => 'Forecast',
            'findings' => 'Findings',
        ],
        'aging' => [
            'receivable' => 'Receivables aging',
            'payable' => 'Payables aging',
        ],
        'unclear' => [
            'title' => 'Unclear cases',
            'none' => 'No unclear cases.',
            'open_items' => ':count open items are not settled in the period.',
            'settlement_without_item' => 'Settlement :id without a matching open item.',
            'settlement_without_source' => 'Settlement :id without a usable source document.',
            'account_without_category' => 'Account :account has no cash-basis line.',
        ],
        'quality' => [
            'headline' => 'What stands in the way of the reports',
            'none' => 'No findings.',
            'drafts' => ':count entries are not posted yet.',
            'unbalanced' => ':count drafts are unbalanced.',
            'blocked_runs' => ':count recurring runs are blocked.',
            'open_expectations' => ':count document expectations are still open.',
            'ten_day_rule' => ':count payments fall between 22 Dec and 10 Jan and belong to the adjacent year by document date (§ 11 (1) sentence 2 EStG).',
            'open_clearing' => ':count clearing accounts are not settled yet.',
            'overdue_filings' => ':count filing deadlines have passed and are not marked as submitted.',
            'kpi' => [
                'drafts' => 'Drafts',
                'unbalanced' => 'Unbalanced',
                'blocked_runs' => 'Blocked runs',
                'open_expectations' => 'Open expectations',
            ],
        ],
        'card' => [
            'trial_balance' => [
                'title' => 'Trial balance',
                'text' => 'Opening, movement and balance per account.',
            ],
            'account_ledger' => [
                'title' => 'Account ledger',
                'text' => 'All movements of an account with drilldown to the entry.',
            ],
            'vat' => [
                'title' => 'VAT',
                'text' => 'Output tax, input tax and payable as a preview.',
            ],
            'euer' => [
                'title' => 'Cash-basis preview',
                'text' => 'Income and expenses by receipt and payment.',
            ],
            'recapitulative' => [
                'title' => 'Recapitulative statement',
                'text' => 'Intra-Community supplies by VAT ID',
            ],
            'pnl' => [
                'title' => 'Result',
                'text' => 'Income and expenses by account groups.',
            ],
            'liquidity' => [
                'title' => 'Liquidity',
                'text' => 'Actual balances, open items and forecast — shown separately.',
            ],
            'quality' => [
                'title' => 'Posting quality',
                'text' => 'Drafts, blocked runs and open expectations.',
            ],
            'journal' => [
                'title' => 'Journal',
                'text' => 'All posted entries in journal order.',
            ],
            'open_items' => [
                'title' => 'Open items',
                'text' => 'Receivables and payables with aging.',
            ],
        ],
    ],

    // Periodenabschluss (Feature 125, MVP-677).
    'closing' => [
        'title' => 'Period closing',
        'menu' => 'Closing',
        'subtitle' => 'Close periods provisionally or definitively — and reopen them with a reason.',
        'blocked_hint' => 'Closing stays locked while any check is red.',
        'reopen_hint' => 'Reopening lifts a posting lock. It is recorded with its reason in the audit chain.',
        'column' => [
            'period' => 'Period',
            'closed_at' => 'Closed',
            'reopened' => 'Reopened',
        ],
        'field' => [
            'reason' => 'Reason',
        ],
        'action' => [
            'soft_close' => 'Close provisionally',
            'close' => 'Close definitively',
            'close_submit' => 'Close period',
            'reopen' => 'Reopen',
            'reopen_submit' => 'Open period',
            'close_year' => 'Close fiscal year',
        ],
        'confirm' => [
            'year' => 'Close the fiscal year? All periods must be closed.',
        ],
        'check' => [
            'no_drafts' => 'No open drafts in the period.',
            'drafts' => ':count entries are not posted yet.',
            'balanced' => 'All entries are balanced.',
            'unbalanced' => ':count entries are unbalanced.',
            'sequence_ok' => 'No earlier periods left open.',
            'earlier_open' => ':count earlier periods are still open.',
            'key' => [
                'drafts' => 'Drafts',
                'balanced' => 'Balance',
                'sequence' => 'Sequence',
            ],
        ],
        'flash' => [
            'soft_closed' => 'Period closed provisionally.',
            'closed' => 'Period closed.',
            'reopened' => 'Period reopened.',
            'year_closed' => 'Fiscal year closed.',
        ],
        'error' => [
            'reason_required' => 'Reopening requires a reason.',
            'already_open' => 'The period is already open.',
            'wrong_status' => 'This step is not possible in state :status.',
            'periods_open' => ':count periods are not closed yet.',
        ],
    ],

    // Startsalden und DATEV-Übergabe (Feature 125, MVP-677).
    'opening' => [
        'title' => 'Import opening balances',
        'subtitle' => 'CSV with account, debit and credit — check first, then post.',
        'hint' => 'The MVP takes opening balance, open items and effective date; a full legacy journal is deliberately not imported.',
        'field' => [
            'file' => 'CSV file',
        ],
        'action' => [
            'dry_run' => 'Dry run',
            'import' => 'Import',
        ],
        'flash' => [
            'dry_run' => 'Dry run: :lines lines, debit :debit, credit :credit, :errors errors.',
            'imported' => 'Opening entry :no created.',
        ],
        'error' => [
            'missing_account' => 'Line :line without account.',
            'unknown_account' => 'Account :account (line :line) does not exist.',
            'both_sides' => 'Line :line carries debit and credit.',
            'unbalanced' => 'Debit (:debit) and credit (:credit) do not match.',
        ],
    ],
    'datev' => [
        'title' => 'DATEV handover',
        'subtitle' => 'Entry lines of the period as CSV.',
        'hint' => 'Generated from posted entries — not derived from the documents again.',
        'action' => [
            'export' => 'Export',
        ],
    ],

    // Kontenplan-Vorlagen (Feature 125, MVP-678).
    'template' => [
        'title' => 'Chart of accounts from template',
        'subtitle' => 'Create accounts, tax codes and posting rules in one step.',
        'hint_first' => 'The template creates accounts, tax codes and matching posting rules — the posting inbox works right away.',
        'hint_additive' => 'Additive only: existing accounts and rules stay untouched.',
        'disclaimer' => 'Starter extract modelled on the respective German standard chart of accounts, for Germany. Account choice and tax mapping need professional review before the first posting.',
        'field' => [
            'template' => 'Template',
        ],
        'action' => [
            'apply' => 'Apply template',
        ],
        'flash' => [
            'applied' => 'Template applied: :accounts accounts, :tax_codes tax codes, :rules rules created, :skipped skipped.',
        ],
        'error' => [
            'unknown' => 'Unknown chart of accounts template: :code',
        ],
    ],

    // Versteuerungsart (Feature 125, MVP-679).
    'taxation' => [
        'title' => 'Taxation method',
        'subtitle' => 'Accrual or cash basis — affects the VAT report only.',
        'current' => 'Current: :method',
        'default_hint' => 'Without a setting, accrual taxation applies (§ 16 (1) UStG).',
        'field' => [
            'method' => 'Taxation method',
            'valid_from' => 'Valid from',
        ],
        'hint' => [
            'method' => 'Cash-basis taxation (§ 20 UStG) needs approval; input tax is unaffected either way.',
            'valid_from' => 'Usually the turn of the year — the next 1 January is suggested.',
        ],
        'column' => [
            'changeover' => 'Open items at the switch',
        ],
        'action' => [
            'switch' => 'Switch taxation method',
            'switch_submit' => 'Record switch',
        ],
        'changeover' => [
            'headline' => ':count open items totalling :amount are affected on the effective date.',
            'hint' => '§ 20 s. 3 UStG: turnover must neither be recorded twice nor stay untaxed. The switch is not blocked — the assessment belongs to the tax adviser.',
            'summary' => ':count items / :amount',
        ],
        'flash' => [
            'switched' => 'Taxation method switched.',
        ],
        'error' => [
            'unchanged' => 'This taxation method already applies on that date.',
            'later_section' => 'A later section already starts on :date.',
        ],
    ],
    // Klärungsbuchung und interne Umbuchung (Feature 125, MVP-681).
    'clearing' => [
        'title' => 'Clearing entry',
        'memo' => 'Unresolved item: :purpose',
        'no_account' => 'No clearing account is set up. Mark an account in the chart of accounts as a clearing account.',
        'action' => [
            'post' => 'Post to clearing account',
            'post_submit' => 'Create clearing entry',
        ],
        'field' => [
            'account' => 'Clearing account',
            'note' => 'Why is this transaction unclear?',
            'follow_up_on' => 'Follow-up date',
        ],
        'hint' => [
            'account' => 'Only accounts explicitly marked as clearing accounts are offered.',
            'note' => 'Required — it is the only record of why nothing was assigned here.',
            'follow_up_on' => 'The case should be resolved by this date.',
        ],
        'error' => [
            'not_a_clearing_account' => 'The selected account is not a clearing account.',
            'note_required' => 'A reason is required.',
        ],
        'blocker' => [
            'unassigned' => 'No assigned document — postable only via an assignment or the clearing account.',
        ],
        'flash' => [
            'posted' => 'Clearing entry created.',
        ],
    ],
    'transfer' => [
        'title' => 'Internal transfer',
        'action' => [
            'record' => 'Internal transfer',
            'record_submit' => 'Post transfer',
        ],
        'field' => [
            'from_account' => 'From account',
            'to_account' => 'To account',
        ],
        'hint' => [
            'note' => 'What was the money moved for (e.g. cash withdrawal for the till)?',
        ],
        'error' => [
            'same_account' => 'Source and target account must differ.',
            'not_a_money_account' => 'Account :account is not a bank, cash or transit account.',
            'amount_positive' => 'The amount must be greater than zero.',
        ],
        'flash' => [
            'recorded' => 'Transfer posted.',
        ],
    ],

    // Meldepflichten der Umsatzsteuer (Feature 125, MVP-684).
    'filing' => [
        'fields' => [
            'title' => 'VAT return field numbers',
            'subtitle' => 'Mapping of tax codes to the fields of the German VAT return — a reconciliation aid, not the form.',
            'tax_codes' => 'Tax codes',
            'remaining' => 'Remaining advance payment (83)',
            'unclear' => 'Tax code :code has no field number.',
            'column' => [
                'field' => 'Field',
                'base' => 'Tax base',
                'tax' => 'Tax amount',
            ],
            'hint' => [
                'base' => 'Field of the tax base, e.g. 81 (19 %), 86 (7 %), 41 (intra-Community supplies).',
                'tax' => 'Field of the tax amount, e.g. 66 (input tax), 61 (intra-Community acquisition).',
            ],
            'flash' => [
                'saved' => 'Field numbers saved.',
            ],
        ],
        'calendar' => [
            'menu' => 'Tax deadlines',
            'title' => 'Tax deadlines',
            'subtitle' => 'VAT deadlines and what has been filed.',
            'hint' => 'Deadlines are calculated (§ 108 (3) AO: weekends and public holidays move to the next business day). Nothing is transmitted.',
            'tax_advised' => 'tax advisor engaged',
            'overdue' => 'Overdue',
            'empty' => 'No deadlines in this range.',
            'column' => [
                'kind' => 'Obligation',
                'due_on' => 'Due',
            ],
            'action' => [
                'submitted' => 'Mark as submitted',
            ],
        ],
        'notification' => [
            'title' => ':kind is due',
            'message' => 'Period :period — due :due.',
        ],
        'no_period' => 'No VAT return period is set for this organisation (small business under § 19 UStG).',
        'prepayment_memo' => 'Special prepayment 1/11 for :year',
        'prepayment' => [
            'title' => 'Post special prepayment',
            'submit' => 'Post special prepayment',
            'calculation' => 'One eleventh from :year: prior-year tax :tax, annualised :annualised → :amount.',
            'annualised_hint' => 'Active only :months months last year — annualised (§ 47 (3) UStDV).',
            'due_hint' => 'File and pay by :date.',
        ],
        'title' => 'Filing obligations',
        'subtitle' => 'VAT return period, permanent deadline extension and due dates.',
        'current' => 'Currently: :interval',
        'default_hint' => 'Without a setting the calendar quarter applies (§ 18 (2) sentence 1 UStG).',
        'field' => [
            'period' => 'Period',
            'remaining' => 'Remaining advance payment',
            'prepayment_account' => 'Special prepayment account',
            'money_account' => 'Cash/bank account',
            'interval' => 'VAT return period',
            'valid_from' => 'Valid from',
            'year' => 'Calendar year',
            'granted_on' => 'Granted on',
            'special_prepayment' => 'Special prepayment (1/11)',
        ],
        'hint' => [
            'prepayment_account' => 'Usually 1781 (SKR03) or 3830 (SKR04) — VAT advance payments 1/11.',
            'interval' => 'The tax office decides the period — the program records that decision.',
            'valid_from' => 'Usually a turn of the year; a mid-year change is possible.',
            'granted_on' => 'Leave empty as long as the extension has not been granted.',
            'special_prepayment' => 'One eleventh of last year’s advance payments; filed and paid by 10 February (§ 47 UStDV).',
        ],
        'action' => [
            'switch' => 'Change period',
            'switch_submit' => 'Apply period',
        ],
        'error' => [
            'note_required' => '“Not required” needs a reason.',
            'amount_positive' => 'The amount must be greater than zero.',
            'not_a_money_account' => 'The selected account is not a bank or cash account.',
            'no_extension' => 'No deadline extension is recorded for :year.',
            'unchanged' => 'This return period already applies on that date.',
            'later_section' => 'A section starting :date already exists. Edit that one first.',
        ],
        'flash' => [
            'marked' => 'Status recorded.',
            'prepayment_posted' => 'Special prepayment posted.',
            'switched' => 'VAT return period changed.',
            'extension_saved' => 'Deadline extension saved.',
        ],
        'suggestion' => [
            'headline' => 'Suggestion from :year (tax :amount): :interval.',
            'monthly' => 'Above €9,000 prior-year tax — monthly (§ 18 (2) sentence 2 UStG).',
            'quarterly' => 'Between €2,000 and €9,000 — calendar quarter (§ 18 (2) sentence 1 UStG).',
            'annual' => 'Up to €2,000 — exemption from advance returns possible (§ 18 (2) sentence 3 UStG).',
            'none' => 'No VAT advance returns (small business under § 19 UStG).',
            'founder_rule' => 'From assessment period 2027 newly founded businesses must file monthly again.',
        ],
        'extension' => [
            'short' => 'with deadline extension',
            'title' => 'Permanent deadline extension',
            'active' => 'Deadline extension since :year',
            'no_prepayment' => 'Quarterly filers get the extension without a special prepayment (§ 46 UStDV).',
            'prepayment_note' => 'Special prepayment :amount recorded for :year.',
        ],
    ],

    // Zusammenfassende Meldung (Feature 125, MVP-687).
    'recapitulative' => [
        'title' => 'Recapitulative statement',
        'hint' => 'Statement under § 18a UStG. The permanent deadline extension does NOT apply here — the deadline stays the 25th day after the period.',
        'due' => 'Due: :date',
        'interval' => 'Reporting period: :interval',
        'total' => 'Intra-Community supplies',
        'column' => [
            'vat_id' => 'VAT ID',
        ],
        'unclear' => [
            'missing_vat_id' => 'Entry :entry (:customer) without the recipient’s VAT ID.',
            'unknown_customer' => 'no customer',
        ],
    ],

];
