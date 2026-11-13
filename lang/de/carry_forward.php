<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : carry_forward.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Merker für die nächste Periode (Feature 201, MVP-1645): Komponente <x-carry-forward-notes>.
return [
    'title' => 'Merker für dieses Mal',
    'empty' => [
        'title' => 'Keine offenen Merker',
        'message' => 'Für :period liegt aus früheren Perioden nichts vor.',
    ],
    'open_heading' => 'Offene Merker aus früheren Perioden',
    'left_by' => 'Von :name in :period hinterlegt, gilt ab :from.',
    'resolution_label' => 'Vermerk zum Abhaken',
    'resolution_placeholder' => 'Kurzer Vermerk (optional), z. B. „Bescheinigung angefordert“',
    'resolve' => 'Abhaken',
    'resolved_heading' => 'Hier abgehakte Merker',
    'resolved_by' => 'Abgehakt von :name am :date',
    'left_heading' => 'Für später hinterlegt',
    'from_period' => 'ab :period',
    'withdraw' => 'Merker zurückziehen',
    'withdraw_confirm' => 'Diesen Merker wirklich zurückziehen? Er erscheint dann beim nächsten Mal nicht.',
    'new_label' => 'Merker fürs nächste Mal',
    'new_hint' => 'Erscheint beim nächsten Vorkommen dieser Reihe oben, bis ihn jemand abhakt. Notizen dieses Vorgangs wandern nicht mit.',
    'target_label' => 'Gilt',
    'create' => 'Merker hinterlegen',
    'help' => 'Wie funktionieren Merker?',
    // Cockpit von Kunde, Lieferant, Projekt und Asset (MVP-1646).
    'cockpit' => [
        'summary' => 'Offene Merker sind nicht abgehakt; sie erscheinen beim nächsten Vorkommen der Reihe ab ihrer Zielperiode.',
        'contracts' => 'Merker an Vertragspflichten',
        'inspections' => 'Merker an Prüfpflichten',
        'recurring_orders' => 'Merker an wiederkehrenden Aufträgen',
        'period_orders' => 'Merker an Aufträgen mit Periode',
    ],
    'flash' => [
        'created' => 'Merker fürs nächste Mal hinterlegt.',
        'resolved' => 'Merker abgehakt.',
        'withdrawn' => 'Merker zurückgezogen.',
    ],
    'error' => [
        'no_series' => 'Dieser Vorgang gehört zu keiner Reihe mit Periode; ein Merker ist hier nicht möglich.',
        'target_not_after' => 'Ein Merker gilt frühestens ab der nächsten Periode.',
        'empty' => 'Bitte geben Sie einen Text für den Merker ein.',
        'foreign_series' => 'Dieser Merker gehört nicht zu dieser Reihe.',
        'already_resolved' => 'Dieser Merker ist bereits abgehakt.',
        'not_due' => 'Dieser Merker gilt erst ab einer späteren Periode.',
    ],
];
