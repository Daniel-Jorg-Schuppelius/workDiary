<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting_migration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Buchhaltungswechsel (Feature 008/045/077, MVP-653).
return [
    'title' => 'Accounting software migration',
    'intro' => 'Plan a migration of the accounting software, check it as a dry run, secure it with parallel operation, switch over on the cutover date and close it with a report. WorkDiary maps both foreign systems to the same local objects — finalized documents are never rebuilt.',
    'plan_heading' => 'Plan migration',
    'plan_hint' => 'Only one migration per organization at a time. The analysis writes to no foreign system.',
    'areas' => 'Data areas',
    'read_only' => 'history only',
    'cutover_on' => 'Cutover date',
    'cutover_hint' => 'From this day, new billing documents are created exclusively in the target system; the source system is blocked for them.',
    'plan_submit' => 'Plan migration',
    'no_cutover' => 'not set yet',
    'dry_run_badge' => 'Dry run',
    'run_heading' => 'Migration :source → :target',
    'analyze' => 'Analysis (dry run)',
    'start_parallel' => 'Start parallel operation',
    'cutover' => 'Switch over',
    'cutover_confirm' => 'Switch over now? From the cutover date, new billing documents are created exclusively in the target system; the source push is blocked.',
    'complete' => 'Complete',
    'report' => 'Report (CSV)',
    'cancel' => 'Cancel',
    'cancel_confirm' => 'Really cancel the migration? Decisions already made are kept as evidence.',
    'blockers_heading' => 'Open items',
    'counters_heading' => 'Counters',
    'area' => 'Area',
    'counter_read' => 'read',
    'counter_matched' => 'mapped',
    'counter_pending' => 'open',
    'counter_conflict' => 'conflicts',
    'items_heading' => 'Records',
    'item_title' => 'Label',
    'item_source' => 'Source',
    'item_target' => 'Target',
    'item_status' => 'Status',
    'item_decision' => 'Decision',
    'history_heading' => 'Past migrations',
    'status.pending' => 'open',
    'status.matched' => 'mapped',
    'status.transferred' => 'transferred',
    'status.conflict' => 'conflict',
    'status.skipped' => 'skipped',
    'status.historic' => 'history',
    'status.failed' => 'failed',
    'source' => 'Source system',
    'target' => 'Target system',
];
