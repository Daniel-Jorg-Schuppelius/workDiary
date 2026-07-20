<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vvt-templates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * VVT-Vorlagenkatalog (Feature 043 MVP 1 „Vorlagen für typische Prozesse";
 * Vollaudit 2026-07, M17): typische Verarbeitungstätigkeiten aus IT-Service
 * und Handwerk plus die drei Finanz-Verarbeitungstätigkeiten aus Feature 045.
 * Anlage über den „Aus Vorlage anlegen"-Pfad im ProcessingActivityController —
 * Entwurfsstatus, org-scoped, idempotent über den Namen. Die Texte sind
 * Startpunkte und MÜSSEN je Organisation geprüft/angepasst werden.
 */
return [
    'it_helpdesk_fernwartung' => [
        'name' => 'Helpdesk & Fernwartung (IT-Service)',
        'purpose' => 'Entgegennahme, Bearbeitung und Dokumentation von Störungen und Serviceanfragen inkl. Fernzugriff auf Kundensysteme.',
        'controller_role' => 'processor',
        'area' => 'IT-Service',
        'payload' => [
            'data_categories' => 'Kontaktdaten der Ansprechpartner (Name, E-Mail, Telefon), Ticketinhalte, Systemkennungen, Verbindungs- und Sitzungsprotokolle der Fernwartung.',
            'legal_basis' => 'Art. 6 Abs. 1 lit. b DSGVO (Vertrag/Servicevertrag); Auftragsverarbeitung nach Art. 28 DSGVO mit AV-Vertrag.',
            'recipients' => 'Interne Servicetechniker; ggf. Hersteller-Support (nur bei eskalierten Fällen).',
            'transfers' => 'Keine Drittlandübermittlung vorgesehen; Fernwartungstools mit EU-Hosting.',
            'retention' => 'Ticket- und Sitzungsprotokolle: Vertragslaufzeit + 3 Jahre (Gewährleistung/Nachweis).',
            'tom' => 'Rollenbasierte Zugriffe, MFA für Fernzugriff, Sitzungsaufzeichnung nur nach Ankündigung, Transportverschlüsselung.',
        ],
    ],
    'handwerk_auftragsabwicklung' => [
        'name' => 'Auftragsabwicklung & Baustellendokumentation (Handwerk)',
        'purpose' => 'Planung, Durchführung und Dokumentation von Kundenaufträgen inkl. Foto-Dokumentation und Abnahmeprotokollen.',
        'controller_role' => 'controller',
        'area' => 'Handwerk',
        'payload' => [
            'data_categories' => 'Kundenstammdaten, Auftrags- und Leistungsdaten, Fotos der Arbeitsstätte, Unterschriften (Abnahme), Ansprechpartner.',
            'legal_basis' => 'Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung); Fotos: berechtigtes Interesse (Dokumentation) bzw. Einwilligung, wenn Personen abgebildet sind.',
            'recipients' => 'Interne Monteure/Disposition; Subunternehmer nur im Auftragsumfang.',
            'transfers' => 'Keine Drittlandübermittlung.',
            'retention' => 'Auftrags-/Abnahmedokumentation: 6–10 Jahre (HGB/AO); Fotos ohne Nachweischarakter kürzer.',
            'tom' => 'Mobile Erfassung mit Gerätesperre, Berechtigungen je Rolle, revisionssichere Ablage der Protokolle.',
        ],
    ],
    'zahlungsabgleich' => [
        'name' => 'Zahlungsabgleich (Bankimport)',
        'purpose' => 'Import von Kontoauszügen und Abgleich offener Forderungen mit Zahlungseingängen (Feature 045).',
        'controller_role' => 'controller',
        'area' => 'Finanzen',
        'payload' => [
            'data_categories' => 'Kontoumsätze (IBAN, Name des Zahlungspflichtigen, Verwendungszweck, Betrag), Rechnungs-/Debitorendaten.',
            'legal_basis' => 'Art. 6 Abs. 1 lit. b und c DSGVO (Vertragsabwicklung, handels-/steuerrechtliche Pflichten).',
            'recipients' => 'Interne Buchhaltung; Kreditinstitute (Abruf der Auszüge).',
            'transfers' => 'Keine Drittlandübermittlung.',
            'retention' => 'Buchungsbelege 10 Jahre (§ 147 AO, § 257 HGB).',
            'tom' => 'Zugriff nur Buchhaltungsrollen, Hash-Duplikatschutz je Datei, revisionssichere Journalführung (GoBD).',
        ],
    ],
    'buchhaltungsuebergabe_kanzlei' => [
        'name' => 'Buchhaltungsübergabe an die Kanzlei',
        'purpose' => 'Periodische Übergabe von Buchungsstapeln und Belegen an die Steuerkanzlei (DATEV-Export, Feature 045).',
        'controller_role' => 'controller',
        'area' => 'Finanzen',
        'payload' => [
            'data_categories' => 'Debitoren-/Kreditorenstammdaten, Rechnungs- und Buchungsdaten, Belege.',
            'legal_basis' => 'Art. 6 Abs. 1 lit. c DSGVO (steuerrechtliche Pflichten); Kanzlei als eigenständiger Verantwortlicher (Berufsrecht).',
            'recipients' => 'Steuerkanzlei; DATEV eG als deren Rechenzentrum.',
            'transfers' => 'Keine Drittlandübermittlung.',
            'retention' => 'Übergabenachweise und Exporte 10 Jahre (§ 147 AO).',
            'tom' => 'Export nur durch Buchhaltungsrollen, Datei-Hash im Übergabenachweis, Doppelübergabe-Guard.',
        ],
    ],
    'lohndatenuebermittlung' => [
        'name' => 'Lohndatenübermittlung',
        'purpose' => 'Monatliche Übermittlung freigegebener Zeit-/Lohndaten an die Lohnabrechnungsstelle (Feature 045).',
        'controller_role' => 'controller',
        'area' => 'Finanzen',
        'payload' => [
            'data_categories' => 'Mitarbeiterstammdaten (Personalnummer, Name), Arbeits-/Fehlzeiten, Zuschläge, Lohnarten.',
            'legal_basis' => 'Art. 6 Abs. 1 lit. b und c DSGVO i. V. m. § 26 BDSG (Beschäftigungsverhältnis, gesetzliche Melde-/Abrechnungspflichten).',
            'recipients' => 'Lohnbüro/Steuerkanzlei; Sozialversicherungsträger und Finanzverwaltung über die Abrechnungsstelle.',
            'transfers' => 'Keine Drittlandübermittlung.',
            'retention' => 'Lohnunterlagen 6–10 Jahre (AO/HGB), SV-Nachweise nach SGB-Vorgaben.',
            'tom' => 'Monatsfreigabe vor Export, Vier-Augen-Prinzip, Exportnachweis mit Hash, Zugriff nur Lohn-/Admin-Rollen.',
        ],
    ],
];
