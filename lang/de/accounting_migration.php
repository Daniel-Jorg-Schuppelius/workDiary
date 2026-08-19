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
    'title' => 'Buchhaltungswechsel',
    'intro' => 'Wechsel der Buchhaltungssoftware planen, als Dry-Run prüfen, im Doppelbetrieb absichern, am Stichtag umschalten und mit Nachweis abschließen. WorkDiary ordnet beide Fremdsysteme denselben lokalen Objekten zu — finalisierte Belege werden nie nachgebaut.',
    'plan_heading' => 'Wechsel planen',
    'plan_hint' => 'Je Organisation ist genau ein Wechsel gleichzeitig möglich. Die Analyse schreibt in kein Fremdsystem.',
    'areas' => 'Datenbereiche',
    'read_only' => 'nur Historie',
    'cutover_on' => 'Stichtag',
    'cutover_hint' => 'Ab diesem Tag entstehen neue Fakturavorgänge ausschließlich im Zielsystem; das Quellsystem ist dafür gesperrt.',
    'plan_submit' => 'Wechsel planen',
    'no_cutover' => 'noch nicht festgelegt',
    'dry_run_badge' => 'Dry-Run',
    'run_heading' => 'Wechsel :source → :target',
    'analyze' => 'Analyse (Dry-Run)',
    'start_parallel' => 'Doppelbetrieb starten',
    'cutover' => 'Umschalten',
    'cutover_confirm' => 'Jetzt umschalten? Ab dem Stichtag entstehen neue Fakturavorgänge ausschließlich im Zielsystem; der Quell-Push wird gesperrt.',
    'complete' => 'Abschließen',
    'report' => 'Protokoll (CSV)',
    'cancel' => 'Abbrechen',
    'cancel_confirm' => 'Wechsel wirklich abbrechen? Bereits getroffene Entscheidungen bleiben als Nachweis erhalten.',
    'blockers_heading' => 'Offene Punkte',
    'counters_heading' => 'Zählwerke',
    'area' => 'Bereich',
    'counter_read' => 'gelesen',
    'counter_matched' => 'zugeordnet',
    'counter_pending' => 'offen',
    'counter_conflict' => 'Konflikte',
    'items_heading' => 'Datensätze',
    'item_title' => 'Bezeichnung',
    'item_source' => 'Quelle',
    'item_target' => 'Ziel',
    'item_status' => 'Status',
    'item_decision' => 'Entscheidung',
    'history_heading' => 'Frühere Wechsel',
    'status.pending' => 'offen',
    'status.matched' => 'zugeordnet',
    'status.transferred' => 'übertragen',
    'status.conflict' => 'Konflikt',
    'status.skipped' => 'übersprungen',
    'status.historic' => 'Historie',
    'status.failed' => 'fehlerhaft',
    'source' => 'Quellsystem',
    'target' => 'Zielsystem',
];
