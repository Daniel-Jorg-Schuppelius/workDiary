<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : costcenter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'rules' => "Kostenstellen-Regeln",
        'rules_subtitle' => "Kostenstellen für den geprüften Zeitexport: je Benutzer, je Team oder als Organisations-Standard.",
        'rules_help' => "Wie funktionieren Kostenstellen-Regeln?",
        'rules_help_text' => "Beim Zeit-Export bekommt jede Zeile die Kostenstelle des Mitarbeiters: zuerst gewinnt eine Benutzer-Regel, dann die Team-Regel mit der höchsten Priorität, zuletzt der Organisations-Standard. Im Prüf-UI des Exports lässt sich die Kostenstelle je Zeile übersteuern.",
        'create_rule' => "Kostenstellen-Regel anlegen",
        'edit_rule' => "Kostenstellen-Regel bearbeiten",
        'empty' => "Keine Kostenstellen-Regeln vorhanden",
    ],

    'field' => [
        'basics' => "Regel",
        'source' => "Quelle",
        'source_help' => "Benutzer-Regeln gewinnen vor Team-Regeln; ohne Zuordnung gilt der Organisations-Standard.",
        'source_default' => "Organisations-Standard",
        'source_user' => "Benutzer",
        'source_team' => "Team",
        'user' => "Benutzer",
        'team' => "Team",
        'choose' => "– bitte wählen –",
        'cost_center' => "Kostenstelle",
        'priority' => "Priorität",
        'priority_help' => "Tie-Breaker bei mehreren Team-Regeln: höhere Priorität gewinnt.",
    ],

    'action' => [
        'create' => "Anlegen",
        'edit' => "Bearbeiten",
        'save' => "Speichern",
        'delete' => "Löschen",
        'delete_confirm' => "Kostenstellen-Regel wirklich löschen? Bestehende Exporte bleiben unverändert.",
    ],

    'flash' => [
        'created' => "Kostenstellen-Regel angelegt.",
        'updated' => "Kostenstellen-Regel aktualisiert.",
        'deleted' => "Kostenstellen-Regel gelöscht.",
    ],
];
