<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : whistleblowing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/*
 * Strings fuer das Hinweisgebermodul (Kategorien u. a.).
 */

return [
    'category' => [
        'corruption' => 'Korruption und Bestechung',
        'fraud' => 'Betrug, Untreue und Diebstahl',
        'money_laundering' => 'Geldwäsche und Terrorismusfinanzierung',
        'procurement' => 'Vergabe- und Wettbewerbsverstöße',
        'data_protection' => 'Datenschutz und Informationssicherheit',
        'product_safety' => 'Produktsicherheit und Verbraucherschutz',
        'environment' => 'Umwelt- und Arbeitsschutzverstöße',
        'discrimination' => 'Diskriminierung, Belästigung und Machtmissbrauch',
        'policy_violation' => 'Verstoß gegen interne Richtlinien',
        'other' => 'Sonstiger möglicher Rechtsverstoß',
    ],
    'status' => [
        'submitted' => 'Eingegangen',
        'acknowledged' => 'Eingang bestätigt',
        'triage' => 'Prüfung',
        'investigating' => 'In Bearbeitung',
        'waiting_reporter' => 'Wartet auf Rückmeldung',
        'referred' => 'Abgegeben',
        'closed_substantiated' => 'Abgeschlossen – bestätigt',
        'closed_unsubstantiated' => 'Abgeschlossen – nicht bestätigt',
        'closed_out_of_scope' => 'Abgeschlossen – ausserhalb Anwendungsbereich',
        'closed_duplicate' => 'Abgeschlossen – Duplikat',
        'retention_review' => 'Aufbewahrungsprüfung',
        'legal_hold' => 'Löschsperre (Legal Hold)',
        'deleted' => 'Gelöscht',
    ],
    'reporter_status' => [
        'received' => 'Eingegangen und in Prüfung',
        'in_progress' => 'In Bearbeitung',
        'awaiting_you' => 'Rückmeldung von Ihnen erbeten',
        'closed' => 'Abgeschlossen',
    ],
    'priority' => [
        'normal' => 'Normal',
        'high' => 'Hoch',
        'critical' => 'Kritisch',
    ],
    'role' => [
        'owner' => 'Fallverantwortung',
        'processor' => 'Bearbeitung',
        'reviewer' => 'Prüfung',
    ],
];
