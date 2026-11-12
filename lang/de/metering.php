<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Zählerstands-Faktura (Feature 116, MVP-605).
return [
    'title' => 'Zählerabrechnung',
    'subtitle' => 'Verbrauchsabrechnung je Kunde und Gerät aus den erfassten Ablesungen',
    'empty' => 'Noch keine Vereinbarung erfasst.',
    'created' => 'Vereinbarung erfasst.',
    'updated' => 'Vereinbarung aktualisiert.',
    'draft_notice' => 'Der Lauf erzeugt ausschließlich Rechnungsentwürfe — geprüft und ausgestellt wird von Hand.',
    'blocked_external' => 'Für diesen Kunden führt ein externes System die Faktura — es entsteht kein Beleg.',
    'run_done' => 'Abgerechnet: :created Entwurf/Entwürfe, :skipped übersprungen.',
    'form_hint' => 'Ohne Endstand in der Periode entsteht kein Entwurf, sondern ein Hinweis — geschätzt wird nichts.',
    'not_runnable' => 'Diese Vereinbarung wird nicht abgerechnet (Status: :status). Zum Abrechnen den Status auf „Aktiv“ setzen.',
    'project_hint' => 'Die Rechnungsentwürfe des Laufs werden diesem Projekt zugeordnet.',
    'status_hint' => 'Pausierte und beendete Vereinbarungen überspringt der Lauf. Nach einer Pause rechnet er ab „Nächste Abrechnung“ nach — das Datum bei Bedarf vorziehen.',
    'tiers' => [
        'legend' => 'Mengenstaffel',
        'hint' => 'Optional. Mit Staffel gilt je Stufe ihr Preis statt des Einheitspreises; gerechnet wird stufenweise auf den Verbrauch nach Abzug der Freimenge. Die erste Stufe beginnt bei 0, leere Zeilen werden ignoriert.',
        'from' => 'Stufe :n ab Menge',
        'price' => 'Stufe :n Preis je Einheit',
        'badge' => 'Staffel (:count Stufen)',
        'badge_title' => 'Statt des Einheitspreises gilt eine Mengenstaffel.',
        'first_from_zero' => 'Die erste Staffelstufe muss bei 0 beginnen, sonst bliebe Verbrauch unberechnet.',
        'duplicate_from' => 'Jede Staffelstufe braucht eine eigene Menge „ab“.',
    ],
    'unit_default' => 'Einheiten',
    'action' => [
        'create' => 'Vereinbarung erfassen',
        'edit' => 'Vereinbarung bearbeiten',
        'run' => 'Jetzt abrechnen',
    ],
    'column' => [
        'title' => 'Bezeichnung',
        'customer' => 'Kunde',
        'asset' => 'Gerät',
        'base_price' => 'Grundpreis',
        'unit_price' => 'Einheitspreis',
        'free_units' => 'Freimenge',
        'unit' => 'Einheit',
        'interval' => 'Takt',
        'interval_count' => 'Faktor',
        'next_run_on' => 'Nächste Abrechnung',
        'end_on' => 'Ende',
        'project' => 'Projekt',
        'status' => 'Status',
    ],
    'interval' => [
        'monthly' => 'monatlich',
        'quarterly' => 'quartalsweise',
        'yearly' => 'jährlich',
    ],
    'status' => [
        'active' => 'Aktiv',
        'paused' => 'Pausiert',
        'ended' => 'Beendet',
    ],
    'skipped' => [
        'heading' => 'Übersprungene Abrechnungen',
        'hint' => 'Ohne Ablesung entsteht keine Rechnung. Ablesung nachtragen und erneut abrechnen.',
        'reason' => [
            'missing_start_reading' => 'Kein Anfangsstand vor der Periode',
            'missing_end_reading' => 'Keine Ablesung in der Periode',
            'negative_consumption' => 'Negativer Verbrauch (Zählerwechsel?)',
            'nothing_to_bill' => 'Kein Verbrauch und kein Grundpreis',
        ],
    ],
    'line' => [
        'base' => ':title — Grundpreis :from bis :to',
        'usage' => ':title — Verbrauch :consumption :unit, davon :free frei',
        'estimated' => '(geschätzte Ablesung)',
    ],
];
