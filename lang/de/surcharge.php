<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : surcharge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'rules' => 'Zuschlagsregeln',
        'rules_subtitle' => 'Nacht-, Wochenend- und Feiertagszuschläge pro Organisation: Zeitfenster, Prozentsatz und Lohnart für die Lohnübergabe.',
        'rules_help' => 'Wie funktionieren Zuschlagsregeln?',
        'rules_help_text' => 'Jede Regel beschreibt zuschlagsfähige Zeiten (Nachtfenster, Samstag, Sonntag, Feiertag oder ein eigenes Zeitfenster) mit Prozentsatz und Lohnart. Beim Zeit-Export werden die Anwesenheiten daran zerlegt und als zusätzliche Export-Zeilen pro Tag ausgewiesen. Überlappen mehrere Regeln, gewinnt der höchste Prozentsatz — Zuschläge werden nicht addiert.',
        'create_rule' => 'Zuschlagsregel anlegen',
        'edit_rule' => 'Zuschlagsregel bearbeiten',
        'empty' => 'Keine Zuschlagsregeln vorhanden',
        'export_summary' => 'Zuschläge je Mitarbeiter:in und Lohnart',
    ],

    'field' => [
        'basics' => 'Grunddaten',
        'code' => 'Code',
        'code_help' => 'Kurzer, eindeutiger Schlüssel (Kleinbuchstaben, Ziffern, ._-), z. B. „night".',
        'label' => 'Bezeichnung',
        'label_placeholder' => 'z. B. Nachtzuschlag',
        'kind' => 'Art',
        'kind_help' => 'Nacht/Benutzerdefiniert nutzen das Zeitfenster; Samstag, Sonntag und Feiertag gelten ganztägig.',
        'window' => 'Zeitfenster',
        'window_help' => 'Nur für Nacht/Benutzerdefiniert. Fenster über Mitternacht (z. B. 23:00–06:00) sind erlaubt und werden korrekt gesplittet.',
        'window_start' => 'Fenster von',
        'window_end' => 'Fenster bis',
        'whole_day' => 'ganztägig',
        'percentage' => 'Zuschlag (%)',
        'payroll' => 'Lohnübergabe',
        'wage_type_code' => 'Lohnart',
        'wage_type_code_help' => 'Lohnart-Nummer für DATEV/Lexware (z. B. 2010). Leer = ohne Lohnart exportieren.',
        'tax_free_limit_pct' => 'Steuerfrei bis (%)',
        'tax_free_limit_pct_help' => "§ 3b EStG als Konfiguration (z. B. Nacht 25/40, Sonntag 50, Feiertag 125/150). Leer = kein Split. Liegt der Zuschlag darüber, wird der Rest als steuerpflichtiger Anteil mit eigener Lohnart ausgewiesen.",
        'taxable_wage_type_code' => 'Lohnart steuerpflichtiger Anteil',
        'taxable_wage_type_code_help' => "Pflicht, sobald die steuerfreie Obergrenze unter dem Zuschlag liegt. Der €-Grundlohn-Deckel bleibt Sache der externen Lohnrechnung.",
        'priority' => 'Priorität',
        'priority_help' => 'Tie-Breaker bei gleichem Prozentsatz: höhere Priorität gewinnt.',
        'validity' => 'Gültigkeit',
        'valid_from' => 'Gültig ab',
        'valid_until' => 'Gültig bis',
        'unlimited' => 'unbegrenzt',
        'active' => 'Aktiv',
        'rule_active' => 'Regel ist aktiv',
        'hours' => 'Stunden',
        'yes' => 'Ja',
        'no' => 'Nein',
    ],

    'action' => [
        'create' => 'Anlegen',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'delete' => 'Löschen',
        'delete_confirm' => 'Zuschlagsregel wirklich löschen? Bestehende Exporte bleiben unverändert.',
    ],

    'flash' => [
        'created' => 'Zuschlagsregel angelegt.',
        'updated' => 'Zuschlagsregel aktualisiert.',
        'deleted' => 'Zuschlagsregel gelöscht.',
    ],

    'validation' => [
        'taxable_wage_type_required' => "Für den steuerpflichtigen Anteil wird eine eigene Lohnart benötigt.",
    ],
];
