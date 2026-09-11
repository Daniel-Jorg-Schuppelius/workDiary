<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale_portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Kundenportal „meine Abos" (Feature 152): Bestand ohne Preise, Einkauf und Belege.
return [
    'title' => 'Meine Abos',
    'menu' => 'Abos',
    'subtitle' => 'Ihre Abos und Lizenzen — auch die Ihrer Endkunden. Preise und Beträge finden Sie auf Ihren Rechnungen.',
    'field' => [
        'product' => 'Bezeichnung / Produkt',
        'holder' => 'Halter',
        'quantity' => 'Menge',
        'term' => 'Laufzeit',
        'interval' => 'Intervall',
        'renewal' => 'Verlängerung',
        'next_period' => 'Nächste Periode',
        'status' => 'Status',
        'kind' => 'Art',
        'period' => 'Zeitraum',
    ],
    'holder' => [
        'end_customer' => 'Endkunde',
    ],
    'term' => [
        'since' => 'seit :date',
        'range' => ':from – :to',
        'running' => 'läuft',
    ],
    'interval' => [
        'yearly' => 'jährlich',
        'monthly' => 'monatlich',
    ],
    'next_period' => [
        'none' => 'keine weitere',
    ],
    'period_status' => [
        'open' => 'offen',
        'billed' => 'berechnet',
        'partial' => 'teilweise berechnet',
        'waived' => 'nicht berechnet',
        'disputed' => 'in Klärung',
    ],
    'periods' => [
        'title' => 'Abrechnungsperioden',
        'hint' => 'Perioden entstehen aus Beginn, Laufzeit und Intervall; „berechnet“ heißt: dazu liegt Ihnen eine Rechnung vor.',
        'empty' => 'Noch keine Perioden geplant.',
    ],
    'empty' => 'Keine Abos hinterlegt.',
    'back' => 'Zur Übersicht',
    'show' => 'Details',
];
