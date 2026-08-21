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
