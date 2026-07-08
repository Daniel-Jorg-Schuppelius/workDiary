<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : iso27001-2022.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Normprofil ISO/IEC 27001:2022 — Informationssicherheit
 * (Feature 044/046, Inkrement A).
 *
 * Orientierungsrahmen auf HLS-Ebene; normspezifische Unterkapitel und
 * Anhänge sind aus einer von der Organisation lizenzierten Quelle zu
 * ergänzen (Import, 044 MVP3).
 *
 * WICHTIG (Urheberrecht): AUSSCHLIESSLICH Referenznummern mit EIGENEN
 * deutschen Kurztiteln — KEINE Normtexte, keine Control-Beschreibungen.
 * Enthalten sind die Hauptkapitel der Harmonized Structure sowie die
 * 93 Annex-A-Referenzen (vier Themenblöcke: A.5 organisatorisch (37),
 * A.6 personell (8), A.7 physisch (14), A.8 technologisch (34)).
 * Schema siehe {@see \App\Services\Isms\NormProfileRegistry}.
 */

return [
    'key' => 'iso27001-2022',
    'norm' => 'ISO/IEC 27001',
    'edition' => '2022',
    'label' => 'ISO/IEC 27001:2022 — Informationssicherheit',
    // Profilrevision + Stichtag der Normfassung (Nachtrag 046a).
    'version' => '1.0',
    'as_of' => '2022-10-25',
    'requirements' => [
        // ── Harmonized Structure (27) ────────────────────────────────────
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

        // ── Annex A.5 Organisatorische Maßnahmen (37) ───────────────────
        ['ref_no' => 'A.5.1', 'title' => 'Informationssicherheitsrichtlinien'],
        ['ref_no' => 'A.5.2', 'title' => 'Rollen und Verantwortlichkeiten der Informationssicherheit'],
        ['ref_no' => 'A.5.3', 'title' => 'Aufgabentrennung'],
        ['ref_no' => 'A.5.4', 'title' => 'Verantwortlichkeiten der Leitung'],
        ['ref_no' => 'A.5.5', 'title' => 'Kontakt zu Behörden'],
        ['ref_no' => 'A.5.6', 'title' => 'Kontakt zu Interessengruppen'],
        ['ref_no' => 'A.5.7', 'title' => 'Bedrohungsanalyse (Threat Intelligence)'],
        ['ref_no' => 'A.5.8', 'title' => 'Informationssicherheit im Projektmanagement'],
        ['ref_no' => 'A.5.9', 'title' => 'Inventar der Informationen und sonstigen Werte'],
        ['ref_no' => 'A.5.10', 'title' => 'Zulässiger Gebrauch von Informationen und Werten'],
        ['ref_no' => 'A.5.11', 'title' => 'Rückgabe von Werten'],
        ['ref_no' => 'A.5.12', 'title' => 'Klassifizierung von Informationen'],
        ['ref_no' => 'A.5.13', 'title' => 'Kennzeichnung von Informationen'],
        ['ref_no' => 'A.5.14', 'title' => 'Informationsübertragung'],
        ['ref_no' => 'A.5.15', 'title' => 'Zugangssteuerung'],
        ['ref_no' => 'A.5.16', 'title' => 'Identitätsmanagement'],
        ['ref_no' => 'A.5.17', 'title' => 'Authentisierungsinformationen'],
        ['ref_no' => 'A.5.18', 'title' => 'Zugangsrechte'],
        ['ref_no' => 'A.5.19', 'title' => 'Informationssicherheit in Lieferantenbeziehungen'],
        ['ref_no' => 'A.5.20', 'title' => 'Informationssicherheit in Lieferantenvereinbarungen'],
        ['ref_no' => 'A.5.21', 'title' => 'Informationssicherheit in der IKT-Lieferkette'],
        ['ref_no' => 'A.5.22', 'title' => 'Überwachung und Änderung von Lieferantendienstleistungen'],
        ['ref_no' => 'A.5.23', 'title' => 'Informationssicherheit bei Cloud-Diensten'],
        ['ref_no' => 'A.5.24', 'title' => 'Planung der Behandlung von Sicherheitsvorfällen'],
        ['ref_no' => 'A.5.25', 'title' => 'Beurteilung und Entscheidung zu Sicherheitsereignissen'],
        ['ref_no' => 'A.5.26', 'title' => 'Reaktion auf Informationssicherheitsvorfälle'],
        ['ref_no' => 'A.5.27', 'title' => 'Lernen aus Informationssicherheitsvorfällen'],
        ['ref_no' => 'A.5.28', 'title' => 'Sammeln von Beweismaterial'],
        ['ref_no' => 'A.5.29', 'title' => 'Informationssicherheit bei Störungen'],
        ['ref_no' => 'A.5.30', 'title' => 'IKT-Bereitschaft für Business Continuity'],
        ['ref_no' => 'A.5.31', 'title' => 'Rechtliche, regulatorische und vertragliche Anforderungen'],
        ['ref_no' => 'A.5.32', 'title' => 'Geistige Eigentumsrechte'],
        ['ref_no' => 'A.5.33', 'title' => 'Schutz von Aufzeichnungen'],
        ['ref_no' => 'A.5.34', 'title' => 'Datenschutz und Schutz personenbezogener Daten'],
        ['ref_no' => 'A.5.35', 'title' => 'Unabhängige Überprüfung der Informationssicherheit'],
        ['ref_no' => 'A.5.36', 'title' => 'Einhaltung von Richtlinien und Normen zur Informationssicherheit'],
        ['ref_no' => 'A.5.37', 'title' => 'Dokumentierte Betriebsabläufe'],

        // ── Annex A.6 Personelle Maßnahmen (8) ──────────────────────────
        ['ref_no' => 'A.6.1', 'title' => 'Sicherheitsüberprüfung von Bewerbern (Screening)'],
        ['ref_no' => 'A.6.2', 'title' => 'Beschäftigungs- und Vertragsbedingungen'],
        ['ref_no' => 'A.6.3', 'title' => 'Bewusstsein, Aus- und Weiterbildung zur Informationssicherheit'],
        ['ref_no' => 'A.6.4', 'title' => 'Maßregelungsprozess'],
        ['ref_no' => 'A.6.5', 'title' => 'Verantwortlichkeiten nach Beendigung oder Wechsel der Tätigkeit'],
        ['ref_no' => 'A.6.6', 'title' => 'Vertraulichkeits- und Geheimhaltungsvereinbarungen'],
        ['ref_no' => 'A.6.7', 'title' => 'Mobiles Arbeiten'],
        ['ref_no' => 'A.6.8', 'title' => 'Meldung von Informationssicherheitsereignissen'],

        // ── Annex A.7 Physische Maßnahmen (14) ──────────────────────────
        ['ref_no' => 'A.7.1', 'title' => 'Physische Sicherheitsperimeter'],
        ['ref_no' => 'A.7.2', 'title' => 'Physischer Zutritt'],
        ['ref_no' => 'A.7.3', 'title' => 'Sicherung von Büros, Räumen und Einrichtungen'],
        ['ref_no' => 'A.7.4', 'title' => 'Physische Sicherheitsüberwachung'],
        ['ref_no' => 'A.7.5', 'title' => 'Schutz vor physischen und umweltbedingten Bedrohungen'],
        ['ref_no' => 'A.7.6', 'title' => 'Arbeiten in Sicherheitsbereichen'],
        ['ref_no' => 'A.7.7', 'title' => 'Aufgeräumte Arbeitsumgebung und Bildschirmsperre'],
        ['ref_no' => 'A.7.8', 'title' => 'Platzierung und Schutz von Geräten'],
        ['ref_no' => 'A.7.9', 'title' => 'Sicherheit von Werten außerhalb der Räumlichkeiten'],
        ['ref_no' => 'A.7.10', 'title' => 'Speichermedien'],
        ['ref_no' => 'A.7.11', 'title' => 'Versorgungseinrichtungen'],
        ['ref_no' => 'A.7.12', 'title' => 'Sicherheit der Verkabelung'],
        ['ref_no' => 'A.7.13', 'title' => 'Instandhaltung von Geräten'],
        ['ref_no' => 'A.7.14', 'title' => 'Sichere Entsorgung oder Wiederverwendung von Geräten'],

        // ── Annex A.8 Technologische Maßnahmen (34) ─────────────────────
        ['ref_no' => 'A.8.1', 'title' => 'Endgeräte der Benutzer'],
        ['ref_no' => 'A.8.2', 'title' => 'Privilegierte Zugangsrechte'],
        ['ref_no' => 'A.8.3', 'title' => 'Beschränkung des Informationszugangs'],
        ['ref_no' => 'A.8.4', 'title' => 'Zugang zu Quellcode'],
        ['ref_no' => 'A.8.5', 'title' => 'Sichere Authentisierung'],
        ['ref_no' => 'A.8.6', 'title' => 'Kapazitätssteuerung'],
        ['ref_no' => 'A.8.7', 'title' => 'Schutz gegen Schadsoftware'],
        ['ref_no' => 'A.8.8', 'title' => 'Handhabung technischer Schwachstellen'],
        ['ref_no' => 'A.8.9', 'title' => 'Konfigurationsmanagement'],
        ['ref_no' => 'A.8.10', 'title' => 'Löschung von Informationen'],
        ['ref_no' => 'A.8.11', 'title' => 'Datenmaskierung'],
        ['ref_no' => 'A.8.12', 'title' => 'Verhinderung von Datenabfluss'],
        ['ref_no' => 'A.8.13', 'title' => 'Sicherung von Informationen (Backup)'],
        ['ref_no' => 'A.8.14', 'title' => 'Redundanz informationsverarbeitender Einrichtungen'],
        ['ref_no' => 'A.8.15', 'title' => 'Protokollierung'],
        ['ref_no' => 'A.8.16', 'title' => 'Überwachung von Aktivitäten'],
        ['ref_no' => 'A.8.17', 'title' => 'Uhrensynchronisation'],
        ['ref_no' => 'A.8.18', 'title' => 'Gebrauch privilegierter Hilfsprogramme'],
        ['ref_no' => 'A.8.19', 'title' => 'Installation von Software auf Produktivsystemen'],
        ['ref_no' => 'A.8.20', 'title' => 'Netzwerksicherheit'],
        ['ref_no' => 'A.8.21', 'title' => 'Sicherheit von Netzwerkdiensten'],
        ['ref_no' => 'A.8.22', 'title' => 'Trennung von Netzwerken'],
        ['ref_no' => 'A.8.23', 'title' => 'Webfilterung'],
        ['ref_no' => 'A.8.24', 'title' => 'Verwendung von Kryptografie'],
        ['ref_no' => 'A.8.25', 'title' => 'Sicherer Entwicklungslebenszyklus'],
        ['ref_no' => 'A.8.26', 'title' => 'Anforderungen an die Anwendungssicherheit'],
        ['ref_no' => 'A.8.27', 'title' => 'Sichere Systemarchitektur und Entwicklungsgrundsätze'],
        ['ref_no' => 'A.8.28', 'title' => 'Sichere Programmierung'],
        ['ref_no' => 'A.8.29', 'title' => 'Sicherheitsprüfung in Entwicklung und Abnahme'],
        ['ref_no' => 'A.8.30', 'title' => 'Ausgegliederte Entwicklung'],
        ['ref_no' => 'A.8.31', 'title' => 'Trennung von Entwicklungs-, Test- und Produktivumgebungen'],
        ['ref_no' => 'A.8.32', 'title' => 'Änderungssteuerung'],
        ['ref_no' => 'A.8.33', 'title' => 'Testinformationen'],
        ['ref_no' => 'A.8.34', 'title' => 'Schutz von Informationssystemen während Audits'],
    ],
];
