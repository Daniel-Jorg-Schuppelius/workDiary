<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : isms-controls.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Referenzkatalog ISO/IEC 27001:2022 Annex A (Feature 044, MVP 1).
 *
 * WICHTIG (Urheberrecht): Hier liegen AUSSCHLIESSLICH die Referenz-Codes
 * und eigene deutsche KURZTITEL — keine Normtexte, keine Control-
 * Beschreibungen. Vollständige Norm-/Control-Texte dürfen nur aus einer
 * vom Kunden lizenzierten Quelle übernommen werden (siehe Feature-Doc 044,
 * Abschnitt „Maßnahmen und Statement of Applicability").
 *
 * Die Titel sind bewusst DEUTSCH und werden beim Import als Datenbestand
 * in isms_controls übernommen (keine UI-Übersetzung; die Organisation
 * kann Titel/Beschreibung danach selbst pflegen).
 *
 * Vier Themenblöcke: A.5 organisatorisch (37), A.6 personell (8),
 * A.7 physisch (14), A.8 technologisch (34) — zusammen 93 Controls.
 */

return [
    'themes' => [
        'A.5' => 'Organisatorische Maßnahmen',
        'A.6' => 'Personelle Maßnahmen',
        'A.7' => 'Physische Maßnahmen',
        'A.8' => 'Technologische Maßnahmen',
    ],

    'controls' => [
        // ── A.5 Organisatorische Maßnahmen (37) ─────────────────────────
        ['code' => 'A.5.1', 'title' => 'Informationssicherheitsrichtlinien'],
        ['code' => 'A.5.2', 'title' => 'Rollen und Verantwortlichkeiten der Informationssicherheit'],
        ['code' => 'A.5.3', 'title' => 'Aufgabentrennung'],
        ['code' => 'A.5.4', 'title' => 'Verantwortlichkeiten der Leitung'],
        ['code' => 'A.5.5', 'title' => 'Kontakt zu Behörden'],
        ['code' => 'A.5.6', 'title' => 'Kontakt zu Interessengruppen'],
        ['code' => 'A.5.7', 'title' => 'Bedrohungsanalyse (Threat Intelligence)'],
        ['code' => 'A.5.8', 'title' => 'Informationssicherheit im Projektmanagement'],
        ['code' => 'A.5.9', 'title' => 'Inventar der Informationen und sonstigen Werte'],
        ['code' => 'A.5.10', 'title' => 'Zulässiger Gebrauch von Informationen und Werten'],
        ['code' => 'A.5.11', 'title' => 'Rückgabe von Werten'],
        ['code' => 'A.5.12', 'title' => 'Klassifizierung von Informationen'],
        ['code' => 'A.5.13', 'title' => 'Kennzeichnung von Informationen'],
        ['code' => 'A.5.14', 'title' => 'Informationsübertragung'],
        ['code' => 'A.5.15', 'title' => 'Zugangssteuerung'],
        ['code' => 'A.5.16', 'title' => 'Identitätsmanagement'],
        ['code' => 'A.5.17', 'title' => 'Authentisierungsinformationen'],
        ['code' => 'A.5.18', 'title' => 'Zugangsrechte'],
        ['code' => 'A.5.19', 'title' => 'Informationssicherheit in Lieferantenbeziehungen'],
        ['code' => 'A.5.20', 'title' => 'Informationssicherheit in Lieferantenvereinbarungen'],
        ['code' => 'A.5.21', 'title' => 'Informationssicherheit in der IKT-Lieferkette'],
        ['code' => 'A.5.22', 'title' => 'Überwachung und Änderung von Lieferantendienstleistungen'],
        ['code' => 'A.5.23', 'title' => 'Informationssicherheit bei Cloud-Diensten'],
        ['code' => 'A.5.24', 'title' => 'Planung der Behandlung von Sicherheitsvorfällen'],
        ['code' => 'A.5.25', 'title' => 'Beurteilung und Entscheidung zu Sicherheitsereignissen'],
        ['code' => 'A.5.26', 'title' => 'Reaktion auf Informationssicherheitsvorfälle'],
        ['code' => 'A.5.27', 'title' => 'Lernen aus Informationssicherheitsvorfällen'],
        ['code' => 'A.5.28', 'title' => 'Sammeln von Beweismaterial'],
        ['code' => 'A.5.29', 'title' => 'Informationssicherheit bei Störungen'],
        ['code' => 'A.5.30', 'title' => 'IKT-Bereitschaft für Business Continuity'],
        ['code' => 'A.5.31', 'title' => 'Rechtliche, regulatorische und vertragliche Anforderungen'],
        ['code' => 'A.5.32', 'title' => 'Geistige Eigentumsrechte'],
        ['code' => 'A.5.33', 'title' => 'Schutz von Aufzeichnungen'],
        ['code' => 'A.5.34', 'title' => 'Datenschutz und Schutz personenbezogener Daten'],
        ['code' => 'A.5.35', 'title' => 'Unabhängige Überprüfung der Informationssicherheit'],
        ['code' => 'A.5.36', 'title' => 'Einhaltung von Richtlinien und Normen zur Informationssicherheit'],
        ['code' => 'A.5.37', 'title' => 'Dokumentierte Betriebsabläufe'],

        // ── A.6 Personelle Maßnahmen (8) ────────────────────────────────
        ['code' => 'A.6.1', 'title' => 'Sicherheitsüberprüfung von Bewerbern (Screening)'],
        ['code' => 'A.6.2', 'title' => 'Beschäftigungs- und Vertragsbedingungen'],
        ['code' => 'A.6.3', 'title' => 'Bewusstsein, Aus- und Weiterbildung zur Informationssicherheit'],
        ['code' => 'A.6.4', 'title' => 'Maßregelungsprozess'],
        ['code' => 'A.6.5', 'title' => 'Verantwortlichkeiten nach Beendigung oder Wechsel der Tätigkeit'],
        ['code' => 'A.6.6', 'title' => 'Vertraulichkeits- und Geheimhaltungsvereinbarungen'],
        ['code' => 'A.6.7', 'title' => 'Mobiles Arbeiten'],
        ['code' => 'A.6.8', 'title' => 'Meldung von Informationssicherheitsereignissen'],

        // ── A.7 Physische Maßnahmen (14) ────────────────────────────────
        ['code' => 'A.7.1', 'title' => 'Physische Sicherheitsperimeter'],
        ['code' => 'A.7.2', 'title' => 'Physischer Zutritt'],
        ['code' => 'A.7.3', 'title' => 'Sicherung von Büros, Räumen und Einrichtungen'],
        ['code' => 'A.7.4', 'title' => 'Physische Sicherheitsüberwachung'],
        ['code' => 'A.7.5', 'title' => 'Schutz vor physischen und umweltbedingten Bedrohungen'],
        ['code' => 'A.7.6', 'title' => 'Arbeiten in Sicherheitsbereichen'],
        ['code' => 'A.7.7', 'title' => 'Aufgeräumte Arbeitsumgebung und Bildschirmsperre'],
        ['code' => 'A.7.8', 'title' => 'Platzierung und Schutz von Geräten'],
        ['code' => 'A.7.9', 'title' => 'Sicherheit von Werten außerhalb der Räumlichkeiten'],
        ['code' => 'A.7.10', 'title' => 'Speichermedien'],
        ['code' => 'A.7.11', 'title' => 'Versorgungseinrichtungen'],
        ['code' => 'A.7.12', 'title' => 'Sicherheit der Verkabelung'],
        ['code' => 'A.7.13', 'title' => 'Instandhaltung von Geräten'],
        ['code' => 'A.7.14', 'title' => 'Sichere Entsorgung oder Wiederverwendung von Geräten'],

        // ── A.8 Technologische Maßnahmen (34) ───────────────────────────
        ['code' => 'A.8.1', 'title' => 'Endgeräte der Benutzer'],
        ['code' => 'A.8.2', 'title' => 'Privilegierte Zugangsrechte'],
        ['code' => 'A.8.3', 'title' => 'Beschränkung des Informationszugangs'],
        ['code' => 'A.8.4', 'title' => 'Zugang zu Quellcode'],
        ['code' => 'A.8.5', 'title' => 'Sichere Authentisierung'],
        ['code' => 'A.8.6', 'title' => 'Kapazitätssteuerung'],
        ['code' => 'A.8.7', 'title' => 'Schutz gegen Schadsoftware'],
        ['code' => 'A.8.8', 'title' => 'Handhabung technischer Schwachstellen'],
        ['code' => 'A.8.9', 'title' => 'Konfigurationsmanagement'],
        ['code' => 'A.8.10', 'title' => 'Löschung von Informationen'],
        ['code' => 'A.8.11', 'title' => 'Datenmaskierung'],
        ['code' => 'A.8.12', 'title' => 'Verhinderung von Datenabfluss'],
        ['code' => 'A.8.13', 'title' => 'Sicherung von Informationen (Backup)'],
        ['code' => 'A.8.14', 'title' => 'Redundanz informationsverarbeitender Einrichtungen'],
        ['code' => 'A.8.15', 'title' => 'Protokollierung'],
        ['code' => 'A.8.16', 'title' => 'Überwachung von Aktivitäten'],
        ['code' => 'A.8.17', 'title' => 'Uhrensynchronisation'],
        ['code' => 'A.8.18', 'title' => 'Gebrauch privilegierter Hilfsprogramme'],
        ['code' => 'A.8.19', 'title' => 'Installation von Software auf Produktivsystemen'],
        ['code' => 'A.8.20', 'title' => 'Netzwerksicherheit'],
        ['code' => 'A.8.21', 'title' => 'Sicherheit von Netzwerkdiensten'],
        ['code' => 'A.8.22', 'title' => 'Trennung von Netzwerken'],
        ['code' => 'A.8.23', 'title' => 'Webfilterung'],
        ['code' => 'A.8.24', 'title' => 'Verwendung von Kryptografie'],
        ['code' => 'A.8.25', 'title' => 'Sicherer Entwicklungslebenszyklus'],
        ['code' => 'A.8.26', 'title' => 'Anforderungen an die Anwendungssicherheit'],
        ['code' => 'A.8.27', 'title' => 'Sichere Systemarchitektur und Entwicklungsgrundsätze'],
        ['code' => 'A.8.28', 'title' => 'Sichere Programmierung'],
        ['code' => 'A.8.29', 'title' => 'Sicherheitsprüfung in Entwicklung und Abnahme'],
        ['code' => 'A.8.30', 'title' => 'Ausgegliederte Entwicklung'],
        ['code' => 'A.8.31', 'title' => 'Trennung von Entwicklungs-, Test- und Produktivumgebungen'],
        ['code' => 'A.8.32', 'title' => 'Änderungssteuerung'],
        ['code' => 'A.8.33', 'title' => 'Testinformationen'],
        ['code' => 'A.8.34', 'title' => 'Schutz von Informationssystemen während Audits'],
    ],
];
