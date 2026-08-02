<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plans.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Plan-/Modul-Katalog (Single Source of Truth fuer die Tier-Zuordnung).
 *
 * Quelle der Wahrheit fuer den PRODUKTIV-Zugang ist die signierte Lizenz
 * (LicensePayload->features). Diese Datei dient als (a) Vorlage, welche
 * Modul-Codes eine Lizenz der jeweiligen Stufe traegt, und (b) Dev-/Fallback,
 * wenn keine nutzbare Lizenz vorliegt – dann steuert das DB-Feld
 * organizations.plan ueber `tiers`.
 */

return [
    // Karenzzeit (Tage) nach Downgrade, bevor Modul-Daten entfernt werden duerfen.
    'grace_days' => 30,

    // Modul-Abhängigkeiten: abhängiges Modul => Voraussetzung. Ist die
    // Voraussetzung (per Tier/Override) inaktiv, wird das abhängige Modul
    // im FeatureFlagResolver mit deaktiviert (Feature 065).
    'requires' => [
        'module.service_desk' => 'module.helpdesk',
        // Punchout-Katalog browst den Artikelstamm (Feature 099, E1).
        'module.b2b_katalog' => 'module.lager',
    ],

    // Plan → enthaltene Modul-Codes. Enterprise ist Superset von Pro.
    'tiers' => [
        'free' => [],
        'pro' => [
            'module.kanban',
            'module.agile_projects',
            'module.helpdesk',
            'module.planung',
            'module.spesen',
            'module.vertrieb',
            'module.fuhrpark',
            'module.liegenschaften',
            'module.auswertungen_team',
            'module.chat',
            'module.datenschutz',
            'module.documents',
            'module.knowledge',
            'module.forms',
            'module.theming',
            'module.dokumentdesign',
            'module.standorterfassung',
            'module.ideas',
            'module.applications',
            'module.investments',
            'module.sustainability',
            'module.claims',
            'module.rental',
            'module.entsorgung',
            'module.asset_compliance',
            'module.contracts',
            'module.kasse',
            'module.domain',
        ],
        'enterprise' => [
            'module.kanban',
            'module.agile_projects',
            'module.helpdesk',
            'module.service_desk',
            'module.planung',
            'module.spesen',
            'module.vertrieb',
            'module.fuhrpark',
            'module.liegenschaften',
            'module.auswertungen_team',
            'module.chat',
            'module.datenschutz',
            'module.documents',
            'module.knowledge',
            'module.forms',
            'module.theming',
            'module.dokumentdesign',
            'module.lohn',
            'module.compliance',
            'module.isms',
            'module.finance',
            'module.lager',
            'module.b2b_katalog',
            'module.bau',
            'module.versand',
            'module.standorterfassung',
            'module.ideas',
            'module.applications',
            'module.investments',
            'module.crisis_management',
            'module.sustainability',
            'module.claims',
            'module.rental',
            'module.entsorgung',
            'module.asset_finance',
            'module.asset_compliance',
            'module.contracts',
            'module.kasse',
            'module.domain',
            'module.ai',
            'protocols.signed',
            'module.sso',
        ],
    ],

    // Menschlich lesbare Labels (Upsell-Seite, Tooltips).
    'labels' => [
        'module.kanban' => 'Kanban',
        'module.dokumentdesign' => 'PDF-Dokumentdesign & Firmenbogen',
        'module.applications' => 'Bewerbungen & Ausschreibungen',
        'module.investments' => 'Investitionsplanung',
        'module.crisis_management' => 'Notfall- & Krisenmanagement',
        'module.sustainability' => 'Nachhaltigkeit & ESG',
        'module.claims' => 'Reklamation & Gewährleistung',
        'module.rental' => 'Geräte- & Maschinenverleih',
        'module.entsorgung' => 'Altgeräte-Entsorgung & Nachweise',
        'module.asset_finance' => 'Leasing & Asset-Verträge',
        'module.asset_compliance' => 'Prüfmittel & Kalibrierung',
        'module.contracts' => 'Vertragsverwaltung & Fristen',
        'module.domain' => 'Domainverwaltung & DomainReselling',
        'module.ai' => 'KI-Assistenz',
        'module.agile_projects' => 'Agiles Projektmanagement',
        'module.helpdesk' => 'Helpdesk',
        'module.service_desk' => 'Service Desk (ITSM)',
        'module.planung' => 'Planung',
        'module.spesen' => 'Reisen & Spesen',
        'module.vertrieb' => 'Vertrieb & Abrechnung',
        'module.kasse' => 'Kassenbuch',
        'module.fuhrpark' => 'Fuhrpark',
        'module.liegenschaften' => 'Liegenschaften',
        'module.auswertungen_team' => 'Team-Auswertungen',
        'module.chat' => 'Chat',
        'module.datenschutz' => 'Datenschutz',
        'module.documents' => 'Dokumente',
        'module.knowledge' => 'Wissensbasis',
        'module.forms' => 'Formulare',
        'module.theming' => 'Eigene Themes',
        'module.lohn' => 'Lohn & SV',
        'module.compliance' => 'Hinweisgebersystem',
        'module.isms' => 'ISMS',
        'module.finance' => 'Finanzschnittstelle',
        'module.lager' => 'Lager & Artikel',
        'module.b2b_katalog' => 'B2B-Katalogzugang (OCI-Punchout)',
        'module.bau' => 'Bau & GAEB',
        'module.versand' => 'Versand & Logistik',
        'module.standorterfassung' => 'Standorterfassung',
        'module.ideas' => 'Ideenlandkarten',
        'module.sso' => 'SSO & Verzeichnisdienste',
    ],

    // Kurze deutsche Beschreibung je Modul (MVP-052 Modulkonfiguration).
    // Nur Module mit Beschreibung gelten als konfigurierbarer Katalogeintrag.
    'descriptions' => [
        'module.kanban' => 'Aufgaben als Kanban-Board organisieren.',
        'module.ai' => 'Optionale KI-Assistenz: Vorschläge aus konfigurierten Provider-Verbindungen (Cloud oder lokal), opt-in je Capability.',
        'module.applications' => 'Auftragsbewerbungen/Ausschreibungsakten und Personalbewerbungen mit Vertragsverhandlung.',
        'module.investments' => 'Investitionsakten mit Varianten, Budgetantrag, Freigabekette und Soll-Ist-Verfolgung.',
        'module.crisis_management' => 'Krisenakten mit Lagebild, Krisenstab, Alarmierung, Maßnahmen, Kommunikation und Übungen.',
        'module.sustainability' => 'ESG-Bewertungen, Aktivitätsdaten mit CO₂e-Faktoren, Maßnahmen, Ziele und VSME-Berichtsvorbereitung.',
        'module.claims' => 'Reklamationsakten mit Bewertung, Entscheidung, RMA-Rückläufern, Maßnahmen, kaufmännischen Folgen und Lieferantenregress.',
        'module.rental' => 'Verleihakten mit Verfügbarkeitskalender, Reservierung, Übergabe-/Rücknahmeprotokollen, Kaution und Faktura-Übergabe.',
        'module.entsorgung' => 'Entsorgungsakten für Altgeräte: Geräteliste mit AVV-Schlüsseln, Datenträger-Behandlung nach DIN 66399, Entsorger-Übergabe und prüffester Kundennachweis im Portal.',
        'module.asset_finance' => 'Leasing- und Finanzierungsakten mit Konditionen-Snapshot, Fristenkalender, Nutzungslimits und Soll-Ist-Sicht.',
        'module.asset_compliance' => 'Prüfprofile, Prüfpflichten, Prüfprotokolle, Kalibrierzertifikate und Einsatzsperren für prüfpflichtige Assets.',
        'module.contracts' => 'Allgemeine Vertragsakten beliebiger Art mit Laufzeit-/Verlängerungslogik, Kündigungsfrist, Indexierungsregel und Obligationen-/Vertragskalender.',
        'module.domain' => 'Domainportfolio mit Kundenzuordnung, Registrierung, Kontakt-/Nameserver-/DNS-Pflege, Verlängerung/Transfer und Buchungsjournal (DomainReselling).',
        'module.agile_projects' => 'Produkt-Backlog, Projektboards (Kanban/Scrum), Sprints und agile Berichte je Projekt.',
        'module.helpdesk' => 'Tickets mit Queues, Konversation, SLA und Omnichannel-Eingang.',
        'module.service_desk' => 'Servicekatalog, Requests mit Genehmigungen, Incident/Problem/Change (setzt Helpdesk voraus).',
        'module.planung' => 'Dienst-/Schichtplanung, Stundenzettel, Touren und Disposition.',
        'module.spesen' => 'Reisekosten, Spesen und Belegerfassung.',
        'module.vertrieb' => 'Kunden, Projekte, Rechnungen und Abrechnung.',
        'module.kasse' => 'GoBD-konformes Kassenbuch: Bareinnahmen/-ausgaben, Storno statt Löschen, Tagesabschluss mit Kassensturz (kein POS/TSE).',
        'module.fuhrpark' => 'Fahrzeuge, Assets, Reservierungen und Energiedaten.',
        'module.liegenschaften' => 'Standorte, Gebäude, Etagen und Räume.',
        'module.auswertungen_team' => 'Team- und Auswertungs-Reports.',
        'module.chat' => 'Interner Team-Chat.',
        'module.datenschutz' => 'Datenschutzmanagement (VVT, AVV, Betroffenenrechte).',
        'module.documents' => 'Dokumentenverwaltung mit Verträgen und Nachweisen.',
        'module.knowledge' => 'Wissensbasis und Problemhistorie.',
        'module.forms' => 'Formular- und Vorlagensystem.',
        'module.theming' => 'Eigene Themes und Branding gestalten.',
        'module.dokumentdesign' => 'Firmenbogen, Druckbereiche, Informationsblöcke und Tabellenstil-Presets für erzeugte PDF-Dokumente.',
        'module.lohn' => 'Lohnzuschläge und Lohnexport.',
        'module.compliance' => 'Hinweisgebersystem (HinSchG).',
        'module.isms' => 'Informationssicherheits-Managementsystem (ISO 27001).',
        'module.finance' => 'Finanz-/DATEV-Schnittstelle.',
        'module.lager' => 'Lagerwirtschaft, Artikelstamm und Fertigung.',
        'module.b2b_katalog' => 'Punchout-Katalog für Einkaufssysteme der B2B-Kunden (OCI 4.0) mit kundenindividuellen Freigaben/Preisen und openTRANS-2.1-Auftragseingang.',
        'module.bau' => 'Bau-/Ausbau: GAEB-Leistungsverzeichnisse, Ordnungszahlen, Aufmaß und Nachträge.',
        'module.versand' => 'Versandlabels erzeugen und Sendungen verfolgen (DHL Paket u. a.).',
        'module.standorterfassung' => 'Standortbasierte Zeiterfassung über Geofences (OwnTracks/Traccar).',
        'module.ideas' => 'Private und gemeinsame Ideenlandkarten (Mindmaps) mit Überführung in Aufgaben, Projekte und Wissen.',
        'module.sso' => 'Single-Sign-on und Verzeichnisdienste (SCIM-Provisionierung, OIDC-SSO, SAML 2.0).',
    ],

    // Funktionsumfang-Presets (Feature 081, MVP-373): reine Schreibhilfe für
    // die Modulkonfiguration (MVP-052) — gespeichert wird nur der Modulstatus
    // (LicenseFlagOverride, deaktivierend). `modules` = aktiv bleibende Module,
    // alle übrigen werden org-deaktiviert; `modules = null` = Vollumfang.
    // Labels/Beschreibungen werden beim Rendern per __() übersetzt.
    'presets' => [
        'schlank' => [
            'label' => 'Schlanker Start',
            'description' => 'Nur der Kern: Auftragsbuch, Zeiterfassung und persönliche Auswertungen — alle Zusatzmodule bleiben ausgeblendet.',
            'modules' => [],
        ],
        'service_handwerk' => [
            'label' => 'Service & Handwerk',
            'description' => 'Kern plus Planung, Reisen & Spesen, Vertrieb, Fuhrpark, Dokumente, Formulare, Wissensbasis und Team-Auswertungen.',
            'modules' => [
                'module.planung',
                'module.spesen',
                'module.vertrieb',
                'module.fuhrpark',
                'module.documents',
                'module.forms',
                'module.knowledge',
                'module.auswertungen_team',
            ],
        ],
        'buero_dienstleistung' => [
            'label' => 'Büro & Dienstleistung',
            'description' => 'Kern plus Kanban, Planung, Vertrieb, Dokumente, Formulare, Wissensbasis, Chat und Team-Auswertungen.',
            'modules' => [
                'module.kanban',
                'module.planung',
                'module.vertrieb',
                'module.documents',
                'module.forms',
                'module.knowledge',
                'module.chat',
                'module.auswertungen_team',
            ],
        ],
        'voll' => [
            'label' => 'Voller Umfang',
            'description' => 'Alle lizenzierten Module aktiv — das Standardverhalten ohne bewusste Auswahl.',
            'modules' => null,
        ],
    ],

    // Route-Namen-Muster → Modul-Code (zentrales Route-Gating durch
    // EnforcePlanModules). Erste passende Regel gewinnt. Nicht gelistete
    // Routen gelten als Core (immer erreichbar). Persoenliche Auswertungen
    // (reports.my-*, reports.work-balance, reports.attendance) bleiben Core.
    'routes' => [
        'admin.sso.*' => 'module.sso', // Feature 057 SSO/SCIM-Admin (Token-Verwaltung)
        'admin.document-design.*' => 'module.dokumentdesign', // Feature 076 PDF-Dokumentdesign/Firmenbogen
        'admin.orgamax.*' => 'module.finance', // Feature 077 orgaMAX-Buchhaltung-Plugin
        'kanban.*' => 'module.kanban',
        'agile.*' => 'module.agile_projects', // Feature 064 — eigenes Präfix (projects.* ist auf module.vertrieb gemappt!)
        // Feature 065: Tickets waren Core — module.helpdesk ist in pro UND
        // enterprise enthalten, damit das Gating keine Bestandsdaten sperrt.
        'service-tickets.*' => 'module.helpdesk',
        'helpdesk.*' => 'module.helpdesk',
        'servicedesk.*' => 'module.service_desk',

        'duty-plans.*' => 'module.planung',
        'schedule.*' => 'module.planung',
        'shift-types.*' => 'module.planung',
        'timesheets.*' => 'module.planung',
        'flex.*' => 'module.planung',
        'tours.*' => 'module.planung',
        'dispatch.*' => 'module.planung',

        'travel-logs.*' => 'module.spesen',
        'expenses.*' => 'module.spesen',
        'per-diem-trips.*' => 'module.spesen',
        'expense-approvals.*' => 'module.spesen',

        'location.*' => 'module.standorterfassung',
        'geofences.*' => 'module.standorterfassung',

        'articles.*' => 'module.lager',
        'warehouses.*' => 'module.lager',
        'inventory.*' => 'module.lager',
        'manufacturing-orders.*' => 'module.lager',
        'manufacturing-planning.*' => 'module.lager',
        'work-centers.*' => 'module.lager',
        'serials.*' => 'module.lager',
        'purchase-orders.*' => 'module.lager',
        'supplier-scorecards.*' => 'module.lager', // Bauturbo Welle D Lieferantenperformance-Scorecards
        'b2b-catalog.*' => 'module.b2b_katalog', // Feature 099 Zugangs-/Freigabe-Verwaltung + Bestell-Upload (Public-Routen sichert ResolveB2bCatalogOrganization per 404)
        'recipe-menus.*' => 'module.lager', // MVP-455 Menü-/Buffetplanung (Partyservice-Kontext prüft der Controller per 404)
        'print-orders.*' => 'module.lager', // MVP-459 Druckaufträge (Druck-Profil-Kontext prüft der Controller per 404)
        'passenger-rides.*' => 'module.fuhrpark', // MVP-456 Fahrtakten (Taxi-Profil-Kontext prüft der Controller per 404)
        'passenger-masterdata.*' => 'module.fuhrpark', // MVP-456 Tarife/Konzessionen/Fahrzeugprofile
        'passenger-settlements.*' => 'module.fuhrpark', // MVP-456 Schichtabrechnung
        'supplier-catalogs.*' => 'module.lager', // Feature 050 Lieferantenkataloge
        'pricing-margin-rules.*' => 'module.lager', // Feature 050 Margenregeln
        'oci-carts.*' => 'module.lager', // Feature 050 OCI-Warenkorb
        'admin.jtl.*' => 'module.lager', // Feature 078 JTL-Wawi-Plugin (externe Bestandsführung)
        'admin.billbee.*' => 'module.lager', // Feature 093 Billbee-Multichannel (Bestellspiegel + Bestandsrückkanal)

        'bill-of-quantities.*' => 'module.bau', // Feature 049 GAEB-Leistungsverzeichnisse
        'gaeb.*' => 'module.bau',               // Feature 049 GAEB-Import/-Export

        'admin.shipments.*' => 'module.versand', // Feature 059 Versand-/Logistik-Anbindung (DHL Paket)

        'tenders.*' => 'module.applications', // Feature 068 Auftragsbewerbungen
        'recruiting.*' => 'module.applications', // Feature 068 Personalbewerbungen
        'applications.*' => 'module.applications', // Feature 068 Vertragsverhandlungen/Berichte
        'careers.*' => 'module.applications', // Feature 068 MVP-437 öffentlicher Karrierebereich
        'investments.*' => 'module.investments', // Feature 069 Investitionsplanung
        'crisis.*' => 'module.crisis_management', // Feature 070 Notfall-/Krisenmanagement
        'sustainability.*' => 'module.sustainability', // Feature 071 Nachhaltigkeit/ESG
        'claims.*' => 'module.claims', // Feature 072 Reklamation/Gewährleistung/Rückläufer
        'rental.*' => 'module.rental', // Feature 073 Geräte-/Maschinenverleih
        'disposal.*' => 'module.entsorgung', // Feature 100 Entsorgungsakte
        'asset-finance.*' => 'module.asset_finance', // Feature 074 Leasing/Finanzierung/Asset-Verträge
        'asset-compliance.*' => 'module.asset_compliance', // Feature 075 Prüfmittel/Eichung/Kalibrierung
        'contracts.*' => 'module.contracts', // Welle D — Allgemeines Contract-Lifecycle-Management
        'admin.ai.*' => 'module.ai', // Feature 025 KI-Assistenz
        'ai.suggestions.*' => 'module.ai', // Feature 084 KI-Leistungstexte an Belegen
        'admin.domain-provider.*' => 'module.domain', // Feature 083 Domainverwaltung/DomainReselling
        'domains.*' => 'module.domain',
        'domain-reseller.*' => 'module.domain',
        'customers.*' => 'module.vertrieb',
        'suppliers.*' => 'module.vertrieb',
        'projects.*' => 'module.vertrieb',
        'invoices.*' => 'module.vertrieb',
        'invoice-schedules.*' => 'module.vertrieb', // MVP-415 wiederkehrende Rechnungen
        'cash-registers.*' => 'module.kasse', // MVP-414 Kassenbuch
        'quotes.*' => 'module.vertrieb', // Angebote (Feature 066, MVP-170) — Portal-Annahme läuft token-basiert außerhalb
        'lexoffice.*' => 'module.vertrieb',
        'events.*' => 'module.vertrieb',
        'event-categories.*' => 'module.vertrieb',
        'permits.*' => 'module.vertrieb', // Veranstalter-Genehmigungen (Behörden/Fristen/Nachweise)
        'materials.*' => 'module.vertrieb', // Abrechnungskatalog (Preis/Steuer/lexoffice/Billing)

        'assets.*' => 'module.fuhrpark',
        'vehicles.*' => 'module.fuhrpark',
        'driver-license-checks.*' => 'module.fuhrpark', // MVP-417 Führerscheinkontrolle
        'vehicle-reservations.*' => 'module.fuhrpark',
        'energy-logs.*' => 'module.fuhrpark',

        'sites.*' => 'module.liegenschaften',
        'buildings.*' => 'module.liegenschaften',
        'floors.*' => 'module.liegenschaften',
        'rooms.*' => 'module.liegenschaften', // Room → Floor → Building → Site

        'chat.*' => 'module.chat',

        'dataprotection.*' => 'module.datenschutz',

        'documents.*' => 'module.documents',

        'knowledge.*' => 'module.knowledge',

        'ideas.*' => 'module.ideas', // Feature 054 Ideenlandkarten

        'form-templates.*' => 'module.forms',
        'form-submissions.*' => 'module.forms',

        // Theme-Editor (Custom-Themes erstellen). Nur das BEARBEITEN ist
        // gegatet — die Anwendung bereits gesetzter Themes läuft über das
        // Layout (ThemeService) und bleibt auch nach einem Downgrade aktiv.
        'admin.themes.*' => 'module.theming',

        'payroll.*' => 'module.lohn',
        'admin.surcharge-rules.*' => 'module.lohn', // Zuschlagsregeln (Feature 005)

        'whistleblowing.internal.*' => 'module.compliance',
        'whistleblowing.portal.*' => 'module.compliance',

        'isms.*' => 'module.isms',

        // E-Rechnungs-EINGANG (Feature 066 §Lizenzierung; Vollaudit 2026-07,
        // M28): Empfangen/Lesen gehört zum lokalen Fakturapaket und darf nicht
        // allein Enterprise vorbehalten sein (Empfangspflicht seit 01.01.2025) —
        // spezifische Regel VOR dem finance.*-Gate, gleiches Modul wie invoices.*.
        'finance.incoming-invoices.*' => 'module.vertrieb',

        // Finanzschnittstelle (Feature 045): Routen kommen in Teil B —
        // das Mapping ist bereits eingetragen, damit EnforcePlanModules
        // neue finance.*-Routen sofort gated.
        'finance.*' => 'module.finance',

        'reports.week-by-user' => 'module.auswertungen_team',
        'reports.month-by-user-team' => 'module.auswertungen_team',
        'reports.coverage' => 'module.auswertungen_team',
        'reports.absences' => 'module.auswertungen_team',
        'reports.sickness' => 'module.auswertungen_team',
        'reports.qualifications' => 'module.auswertungen_team',
        'reports.customers' => 'module.auswertungen_team',
        'reports.entry-types' => 'module.auswertungen_team',
        'reports.assets' => 'module.auswertungen_team',
        'reports.customer-project' => 'module.auswertungen_team',
        'reports.project-details' => 'module.auswertungen_team',
        'reports.project-inactive' => 'module.auswertungen_team',
        'reports.operations' => 'module.auswertungen_team',
        'reports.economics' => 'module.auswertungen_team',
        'reports.arbzg-compliance' => 'module.auswertungen_team',
    ],

    // Duerfen die Daten eines Moduls nach Ablauf der Karenz geloescht werden?
    // FALSE = gesetzliche Aufbewahrung (GoBD/HinSchG/ArbZG) → niemals
    // automatisch loeschen, nur den Zugriff sperren. Diese Module folgen
    // ausschliesslich ihren eigenen, gesetzlichen Loeschfristen.
    'purgeable_on_downgrade' => [
        'module.kanban' => true,
        'module.planung' => false,          // Stundenzettel/Arbeitszeit → ArbZG
        'module.spesen' => false,           // Belege → GoBD
        'module.vertrieb' => false,
        'module.applications' => false,     // Bewerber-/Vergabedaten → AGG-/Nachweisfristen, nie auto-löschen
        'module.investments' => false,      // Freigabe-/Budget-Nachweise → Aufbewahrung
        'module.crisis_management' => false, // Krisen-/Meldenachweise → Aufbewahrung
        'module.sustainability' => false,   // Bewertungs-/Berichtsnachweise → Aufbewahrung         // Rechnungen → GoBD / §147 AO (10 J.)
        'module.claims' => false,           // Reklamations-/Gewährleistungsnachweise → Aufbewahrung
        'module.rental' => false,           // Übergabe-/Rücknahme-/Abrechnungsnachweise → Aufbewahrung
        'module.entsorgung' => false,       // Entsorgungs-/Datenschutznachweise (ElektroG/NachwV/DSGVO) → Aufbewahrung
        'module.asset_finance' => false,    // Vertrags-/Fristen-/Kostennachweise → Aufbewahrung
        'module.asset_compliance' => false, // Prüfprotokolle/Zertifikate → unveränderbare Nachweise
        'module.contracts' => false,        // Vertrags-/Fristennachweise → Aufbewahrung (GoBD/§147 AO)
        'module.fuhrpark' => true,
        'module.liegenschaften' => true,
        'module.auswertungen_team' => true, // nur Auswertungen, keine Primaerdaten
        'module.chat' => true,
        'module.datenschutz' => false,      // VVT/Vorfaelle → Rechenschaft/Nachweis (Art. 5 Abs. 2)
        'module.documents' => false,        // Vertraege/Zertifikate/Nachweise → Aufbewahrungspflichten (GoBD/§147 AO)
        'module.knowledge' => true,         // org-eigenes Betriebswissen, keine gesetzliche Aufbewahrung
        'module.forms' => false,            // ausgefuellte Formulare koennen Nachweise sein (Pruef-/Abnahmeprotokolle)
        'module.theming' => false,          // rein kosmetische Org-Settings → nie purgen; nach Downgrade bleibt das Theme aktiv, nur der Editor ist gesperrt
        'module.lohn' => false,             // Lohn/SV → GoBD / §147 AO / SGB IV (6 J.)
        'module.compliance' => false,       // Hinweisgeber → HinSchG (3 J.)
        'module.isms' => false,             // Risikoregister/SoA → Compliance-Nachweise (Auditfähigkeit)
        'module.finance' => false,          // Übergabenachweise/Exportpakete → GoBD / §147 AO (10 J.)
        'module.versand' => false,          // Versandbelege/Labels → Handelsbriefe (§147 AO), nie automatisch purgen
        'module.b2b_katalog' => false,      // eingegangene Bestellungen → Handelsbriefe (§147 AO), nie automatisch purgen
        'module.ideas' => false,            // Karten werden bei Downgrade NIE gelöscht, nur unzugänglich (DoD Feature 054)
        'module.agile_projects' => false,   // Boards/Backlog bleiben bei Downgrade erhalten (Vorgabe 064)
        'module.helpdesk' => false,         // Tickets sind aufbewahrungspflichtige Kundenhistorie (065)
        'module.service_desk' => false,     // Problem/Change-Nachweise bleiben (065)
    ],

    // Modul → org-scoped Modelle, die `plans:purge` nach Ablauf der Karenz
    // loescht (nur fuer purgeable=true). Reihenfolge child-first; DB-FK-Cascade
    // (organization_id/parent cascadeOnDelete) sichert verbleibende Kinder.
    // Module ohne eigene Primaertabelle (Kanban = Board-Ansicht,
    // Team-Auswertungen = nur Sichten) haben eine leere Liste → No-op.
    'purge_models' => [
        'module.kanban' => [],
        'module.auswertungen_team' => [],
        'module.fuhrpark' => [
            \App\Models\VehicleReservation::class,
            \App\Models\EnergyLog::class,
            \App\Models\Vehicle::class,
            \App\Models\Asset::class,
        ],
        'module.liegenschaften' => [
            \App\Models\Room::class,
            \App\Models\Floor::class,
            \App\Models\Building::class,
            \App\Models\Site::class,
        ],
        'module.chat' => [
            \App\Models\Chat\Message::class,
            \App\Models\Chat\Channel::class,
        ],
        // Links/Feedback hängen per FK-Cascade am Artikel.
        'module.knowledge' => [
            \App\Models\KnowledgeArticle::class,
        ],
    ],
];
