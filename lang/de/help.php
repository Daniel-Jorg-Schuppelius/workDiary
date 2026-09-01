<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : help.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Hilfecenter (Feature 039, MVP-752): Themenbereiche der Übersicht.
// Keys spiegeln config/help-center.php — Parität ×5 Sprachen ist Pflicht.
return [
    // Artikelschema der Pilotartikel (MVP-756) — Reihenfolge wie
    // config('help-center.article_schema').
    'schema' => [
        'zweck' => 'Zweck und Hintergrund',
        'voraussetzungen' => 'Voraussetzungen',
        'ablauf' => 'Empfohlener Ablauf',
        'beispiel' => 'Beispiel aus der Praxis',
        'fehler' => 'Typische Fehler',
        'naechste-schritte' => 'Auswirkungen und nächste Schritte',
    ],
    'sections' => [
        'erste-schritte' => [
            'title' => 'Erste Schritte',
            'description' => 'Anmeldung, Dashboard, Navigation, eigene Einstellungen und die wichtigsten Abläufe für den Start.',
        ],
        'kunden-vertrieb' => [
            'title' => 'Kunden & Vertrieb',
            'description' => 'Kundenstamm, Fallakte, Projekte, Kundenportal, Termine und Vertriebsthemen.',
        ],
        'zeit-personal' => [
            'title' => 'Zeit & Personal',
            'description' => 'Stempeluhr, Zeitbuchungen, Abwesenheiten, Dienstplanung, Zeitkonten und Lohnexport.',
        ],
        'auftraege-service' => [
            'title' => 'Aufträge & Service',
            'description' => 'Auftragsbuch, Protokolle, Prozeduren, Formulare, Helpdesk und Baustellenthemen.',
        ],
        'material-lager' => [
            'title' => 'Artikel & Lager',
            'description' => 'Artikelstamm, Kataloge, Lagerbestand, Beschaffung, Preise und Seriennummern.',
        ],
        'geraete-fuhrpark' => [
            'title' => 'Geräte & Fuhrpark',
            'description' => 'Geräteakte, Prüfungen, Fahrzeuge, Schlüsselübergaben, Garantien und Software.',
        ],
        'faktura' => [
            'title' => 'Rechnungen & Faktura',
            'description' => 'Angebote, Rechnungen, E-Rechnung, Verträge, Belegfluss und Provisionen.',
        ],
        'buchhaltung' => [
            'title' => 'Buchhaltung & Finanzen',
            'description' => 'Journal, Kontenrahmen, Abschluss, Bankkonten, DATEV- und Zeit-Export.',
        ],
        'auswertungen' => [
            'title' => 'Auswertungen',
            'description' => 'Berichte, Drilldowns, Exporte und Kennzahlen richtig lesen.',
        ],
        'sicherheit-compliance' => [
            'title' => 'Sicherheit & Compliance',
            'description' => 'ISMS, Datenschutz, Hinweisgebersystem, Arbeitsschutz, Audit und Archiv.',
        ],
        'administration' => [
            'title' => 'Administration',
            'description' => 'Organisation, Rollen und Rechte, Import, Sicherung, Lizenz und Integrationen.',
        ],
        'weitere' => [
            'title' => 'Weitere Themen',
            'description' => 'Alles, was keinem der Kernbereiche zugeordnet ist.',
        ],
    ],
];
