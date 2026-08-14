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
        'money_laundering' => 'Geldwaesche und Terrorismusfinanzierung',
        'procurement' => 'Vergabe- und Wettbewerbsverstoesse',
        'data_protection' => 'Datenschutz und Informationssicherheit',
        'product_safety' => 'Produktsicherheit und Verbraucherschutz',
        'environment' => 'Umwelt- und Arbeitsschutzverstoesse',
        'discrimination' => 'Diskriminierung, Belaestigung und Machtmissbrauch',
        'policy_violation' => 'Verstoss gegen interne Richtlinien',
        'other' => 'Sonstiger moeglicher Rechtsverstoss',
    ],
    'status' => [
        'submitted' => 'Eingegangen',
        'acknowledged' => 'Eingang bestaetigt',
        'triage' => 'Pruefung',
        'investigating' => 'In Bearbeitung',
        'waiting_reporter' => 'Wartet auf Rueckmeldung',
        'referred' => 'Abgegeben',
        'closed_substantiated' => 'Abgeschlossen – bestaetigt',
        'closed_unsubstantiated' => 'Abgeschlossen – nicht bestaetigt',
        'closed_out_of_scope' => 'Abgeschlossen – ausserhalb Anwendungsbereich',
        'closed_duplicate' => 'Abgeschlossen – Duplikat',
        'retention_review' => 'Aufbewahrungspruefung',
        'legal_hold' => 'Loeschsperre (Legal Hold)',
        'deleted' => 'Geloescht',
    ],
    'reporter_status' => [
        'received' => 'Eingegangen und in Pruefung',
        'in_progress' => 'In Bearbeitung',
        'awaiting_you' => 'Rueckmeldung von Ihnen erbeten',
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
        'reviewer' => 'Pruefung',
    ],
];
