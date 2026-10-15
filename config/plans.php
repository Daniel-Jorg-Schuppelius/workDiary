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
    // Modulcodes, Labels, Beschreibungen, Routen-Gates und Abhängigkeiten
    // liegen seit MVP-861 in den Manifesten unter app/Modules/Manifests.

    // Karenzzeit (Tage) nach Downgrade, bevor Modul-Daten entfernt werden duerfen.
    'grace_days' => 30,

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
            'module.lms',
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
            'module.reselling',
            'module.club',
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
            'module.lms',
            'module.forms',
            'module.theming',
            'module.dokumentdesign',
            'module.lohn',
            'module.compliance',
            'module.isms',
            'module.finance',
            'module.reselling',
            'module.club',
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

    // Funktionsumfang-Presets (Feature 081, MVP-373): reine Schreibhilfe für
    // die Modulkonfiguration (MVP-052) — gespeichert wird nur der Modulstatus
    // (LicenseFlagOverride, deaktivierend). `modules` = aktiv bleibende Module,
    // alle übrigen werden org-deaktiviert; `modules = null` = Vollumfang.
    // Labels/Beschreibungen werden beim Rendern per __() übersetzt.
    'presets' => [
        // Achtung: Kunden, Projekte, Angebote und Rechnungen hängen an
        // module.vertrieb — dieses Preset blendet sie mit aus (Feature 165, B1).
        'schlank' => [
            'label' => 'Schlanker Start',
            'description' => 'Nur der Kern: Auftragsbuch, Zeiterfassung und persönliche Auswertungen — alle Zusatzmodule bleiben ausgeblendet, auch Kunden, Projekte und Rechnungen.',
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

    // Produktpakete (Feature 165, MVP-1187): Verkaufsbündel vorhandener Module
    // plus Einstiegsvorgaben. Die Lizenz trägt den Paketnamen nur als
    // Metadatum; freigeschaltet wird über `addons`, die die Ausstellung aus
    // `modules` befüllt (kumulativ über `includes`). `default_active` = beim
    // kompakten Einstieg aktive Module, `workspaces` = angebotene kompakte
    // Arbeitsbereiche (config/navigation_focus.php), `focus_roles` /
    // `startpages` = Vorschläge je Rolle. `version` steuert das Angebot einer
    // neuen Vorgabe an Bestandsorganisationen (nie automatisch).
    'packages' => [
        'start' => [
            'label' => 'Start',
            'description' => 'Kundenaufträge und Arbeitszeit einfach organisieren: Kunden, Projekte, Aufträge und die Heute-Seite mit Timer; Angebote und Rechnungen bei Bedarf.',
            'version' => 1,
            'includes' => null,
            'modules' => ['module.vertrieb'],
            'default_active' => ['module.vertrieb'],
            'workspaces' => ['compact_day', 'compact_orders'],
            'commercial_workspace' => 'compact_billing',
            'focus_roles' => [
                'admin' => 'compact_orders',
                'geschaeftsfuehrung' => 'compact_orders',
                'personalverwaltung' => 'compact_orders',
                'teamleitung' => 'compact_orders',
                'buchhaltung' => 'compact_orders',
                'user' => 'compact_day',
                'aussendienst' => 'compact_day',
                'callcenter' => 'compact_day',
                'support' => 'compact_day',
                'training_manager' => 'compact_day',
            ],
            'startpages' => [
                'admin' => 'duties.index',
                'geschaeftsfuehrung' => 'duties.index',
                'personalverwaltung' => 'duties.index',
                'teamleitung' => 'duties.index',
                'buchhaltung' => 'duties.index',
                'user' => 'today.show',
                'aussendienst' => 'today.show',
                'callcenter' => 'today.show',
                'support' => 'today.show',
                'training_manager' => 'today.show',
            ],
        ],
        'team' => [
            'label' => 'Team',
            'description' => 'Arbeit gemeinsam planen und abstimmen: Start plus Dienst- und Einsatzplanung, Stundenzettel, Anwesenheitsboard und Team-Auswertungen.',
            'version' => 1,
            'includes' => 'start',
            'modules' => ['module.planung', 'module.auswertungen_team'],
            'default_active' => ['module.planung', 'module.auswertungen_team'],
            'workspaces' => ['compact_team'],
            'focus_roles' => [
                'teamleitung' => 'compact_team',
                'personalverwaltung' => 'compact_team',
            ],
            'startpages' => [],
        ],
        'betrieb' => [
            'label' => 'Betrieb',
            'description' => 'Abläufe und Wirtschaftlichkeit steuern: Team plus Dokumente, Formulare, Verträge, Finanzübergaben, Mahnwesen und Projektwirtschaftlichkeit — bewusst zuschaltbar.',
            'version' => 1,
            'includes' => 'team',
            'modules' => ['module.documents', 'module.forms', 'module.contracts', 'module.finance'],
            // Betriebsmodule sind lizenziert, aber bis zur bewussten Aktivierung
            // deaktiviert (Feature 165 §5.2).
            'default_active' => [],
            'workspaces' => [],
            'optional_workspaces' => ['compact_company'],
            'focus_roles' => [],
            'startpages' => [],
        ],
    ],

    // Fachliche Erweiterungen (Feature 165 §4.3): Bündel vorhandener Module,
    // die zu jedem Paket hinzukommen; `workspace` = vorhandener Fachbereich.
    'bundles' => [
        'lager' => [
            'label' => 'Lager & Beschaffung',
            'description' => 'Artikel, Bestände, Bestellungen und Fertigung.',
            'modules' => ['module.lager'],
            'workspace' => 'inventory',
        ],
        'service' => [
            'label' => 'Service & Support',
            'description' => 'Tickets, Queues und SLAs für den Support.',
            'modules' => ['module.helpdesk'],
            'workspace' => 'service',
        ],
        'fuhrpark_reisen' => [
            'label' => 'Fuhrpark & Reisen',
            'description' => 'Fahrzeuge, Fahrtenbuch, Reisen und Spesen.',
            'modules' => ['module.fuhrpark', 'module.spesen'],
            'workspace' => 'time',
        ],
        'gebaeude' => [
            'label' => 'Gebäude & Wartung',
            'description' => 'Standorte, Gebäude, Prüfpflichten und Wartung.',
            'modules' => ['module.liegenschaften', 'module.asset_compliance'],
            'workspace' => 'facility',
        ],
        'personal' => [
            'label' => 'Personal & Qualifikation',
            'description' => 'Schulungen, Nachweise und Lernplattform.',
            'modules' => ['module.lms'],
            'workspace' => 'people',
        ],
        'compliance' => [
            'label' => 'Compliance & Datenschutz',
            'description' => 'Datenschutz, ISMS und Hinweisgebersystem.',
            'modules' => ['module.datenschutz', 'module.isms', 'module.compliance'],
            'workspace' => 'compliance',
        ],
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
        'module.club' => false,             // Mitglieder-/Gruppennachweise → Aufbewahrung
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
        'module.reselling' => false,        // Rechnungsbezüge sind Nachweise zu Ausgangsrechnungen → §147 AO
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
            \App\Models\Fleet\VehicleReservation::class,
            \App\Models\Asset\EnergyLog::class,
            \App\Models\Fleet\Vehicle::class,
            \App\Models\Asset\Asset::class,
        ],
        'module.liegenschaften' => [
            \App\Models\Facility\Room::class,
            \App\Models\Facility\Floor::class,
            \App\Models\Facility\Building::class,
            \App\Models\Facility\Site::class,
        ],
        'module.chat' => [
            \App\Models\Chat\Message::class,
            \App\Models\Chat\Channel::class,
        ],
        // Links/Feedback hängen per FK-Cascade am Artikel.
        'module.knowledge' => [
            \App\Models\Knowledge\KnowledgeArticle::class,
        ],
    ],
];
