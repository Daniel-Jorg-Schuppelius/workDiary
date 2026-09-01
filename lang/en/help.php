<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : help.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Help center (feature 039, MVP-752): topic sections of the overview page.
return [
    // Artikelschema der Pilotartikel (MVP-756) — Reihenfolge wie
    // config('help-center.article_schema').
    'schema' => [
        'zweck' => 'Purpose and background',
        'voraussetzungen' => 'Requirements',
        'ablauf' => 'Recommended workflow',
        'beispiel' => 'Practical example',
        'fehler' => 'Common mistakes',
        'naechste-schritte' => 'Effects and next steps',
    ],
    'sections' => [
        'erste-schritte' => [
            'title' => 'Getting started',
            'description' => 'Sign-in, dashboard, navigation, personal settings and the most important first steps.',
        ],
        'kunden-vertrieb' => [
            'title' => 'Customers & sales',
            'description' => 'Customer master data, case file, projects, customer portal, appointments and sales topics.',
        ],
        'zeit-personal' => [
            'title' => 'Time & staff',
            'description' => 'Time clock, time entries, absences, duty planning, time accounts and payroll export.',
        ],
        'auftraege-service' => [
            'title' => 'Orders & service',
            'description' => 'Order book, protocols, procedures, forms, helpdesk and construction-site topics.',
        ],
        'material-lager' => [
            'title' => 'Articles & warehouse',
            'description' => 'Article master, catalogues, stock, procurement, prices and serial numbers.',
        ],
        'geraete-fuhrpark' => [
            'title' => 'Equipment & fleet',
            'description' => 'Asset file, inspections, vehicles, key handovers, warranties and software.',
        ],
        'faktura' => [
            'title' => 'Invoices & billing',
            'description' => 'Quotes, invoices, e-invoicing, contracts, document flow and commissions.',
        ],
        'buchhaltung' => [
            'title' => 'Accounting & finance',
            'description' => 'Journal, chart of accounts, closing, bank accounts, DATEV and time export.',
        ],
        'auswertungen' => [
            'title' => 'Reports',
            'description' => 'Reports, drill-downs, exports and how to read the key figures.',
        ],
        'sicherheit-compliance' => [
            'title' => 'Security & compliance',
            'description' => 'ISMS, data protection, whistleblowing, occupational safety, audit and archive.',
        ],
        'administration' => [
            'title' => 'Administration',
            'description' => 'Organisation, roles and permissions, import, backup, licence and integrations.',
        ],
        'weitere' => [
            'title' => 'Other topics',
            'description' => 'Everything that does not belong to one of the core areas.',
        ],
    ],
];
