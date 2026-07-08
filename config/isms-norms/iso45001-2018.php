<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : iso45001-2018.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Normprofil ISO 45001:2018 — Arbeits- und Gesundheitsschutz (Feature 046, Inkrement A).
 *
 * Orientierungsrahmen auf HLS-Ebene; normspezifische Unterkapitel und
 * Anhänge sind aus einer von der Organisation lizenzierten Quelle zu
 * ergänzen (Import, 044 MVP3).
 *
 * WICHTIG (Urheberrecht): AUSSCHLIESSLICH die Hauptkapitel der Harmonized
 * Structure mit EIGENEN deutschen Kurztiteln — KEINE Normtexte, keine
 * normspezifischen Unterkapitel. Schema siehe
 * {@see \App\Services\Isms\NormProfileRegistry}.
 */

return [
    'key' => 'iso45001-2018',
    'norm' => 'ISO 45001',
    'edition' => '2018',
    'label' => 'ISO 45001:2018 — Arbeits- und Gesundheitsschutz',
    // Profilrevision + Stichtag der Normfassung (Nachtrag 046a).
    'version' => '1.0',
    'as_of' => '2018-03-12',
    'requirements' => [
        ['ref_no' => '4', 'title' => 'Kontext der Organisation'],
        ['ref_no' => '4.1', 'title' => 'Organisation und ihr Kontext verstehen'],
        ['ref_no' => '4.2', 'title' => 'Interessierte Parteien und ihre Anforderungen'],
        ['ref_no' => '4.3', 'title' => 'Anwendungsbereich festlegen'],
        ['ref_no' => '4.4', 'title' => 'Managementsystem und Prozesse'],
        ['ref_no' => '5', 'title' => 'Führung'],
        ['ref_no' => '5.1', 'title' => 'Führung und Verpflichtung'],
        ['ref_no' => '5.2', 'title' => 'Politik/Leitlinie'],
        ['ref_no' => '5.3', 'title' => 'Rollen, Verantwortlichkeiten und Befugnisse'],
        ['ref_no' => '6', 'title' => 'Planung'],
        ['ref_no' => '6.1', 'title' => 'Risiken und Chancen'],
        ['ref_no' => '6.2', 'title' => 'Ziele und Planung zur Erreichung'],
        ['ref_no' => '7', 'title' => 'Unterstützung'],
        ['ref_no' => '7.1', 'title' => 'Ressourcen'],
        ['ref_no' => '7.2', 'title' => 'Kompetenz'],
        ['ref_no' => '7.3', 'title' => 'Bewusstsein'],
        ['ref_no' => '7.4', 'title' => 'Kommunikation'],
        ['ref_no' => '7.5', 'title' => 'Dokumentierte Information'],
        ['ref_no' => '8', 'title' => 'Betrieb'],
        ['ref_no' => '8.1', 'title' => 'Betriebliche Planung und Steuerung'],
        ['ref_no' => '9', 'title' => 'Bewertung der Leistung'],
        ['ref_no' => '9.1', 'title' => 'Überwachung, Messung, Analyse und Bewertung'],
        ['ref_no' => '9.2', 'title' => 'Internes Audit'],
        ['ref_no' => '9.3', 'title' => 'Managementbewertung'],
        ['ref_no' => '10', 'title' => 'Verbesserung'],
        ['ref_no' => '10.1', 'title' => 'Fortlaufende Verbesserung'],
        ['ref_no' => '10.2', 'title' => 'Nichtkonformität und Korrekturmaßnahmen'],
    ],
];
