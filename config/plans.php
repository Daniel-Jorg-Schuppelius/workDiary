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

    // Plan → enthaltene Modul-Codes. Enterprise ist Superset von Pro.
    'tiers' => [
        'free' => [],
        'pro' => [
            'module.kanban',
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
        ],
        'enterprise' => [
            'module.kanban',
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
            'module.lohn',
            'module.compliance',
            'module.isms',
            'module.finance',
            'protocols.signed',
        ],
    ],

    // Menschlich lesbare Labels (Upsell-Seite, Tooltips).
    'labels' => [
        'module.kanban' => 'Kanban',
        'module.planung' => 'Planung',
        'module.spesen' => 'Reisen & Spesen',
        'module.vertrieb' => 'Vertrieb & Abrechnung',
        'module.fuhrpark' => 'Fuhrpark',
        'module.liegenschaften' => 'Liegenschaften',
        'module.auswertungen_team' => 'Team-Auswertungen',
        'module.chat' => 'Chat',
        'module.datenschutz' => 'Datenschutz',
        'module.documents' => 'Dokumente',
        'module.knowledge' => 'Wissensbasis',
        'module.forms' => 'Formulare',
        'module.lohn' => 'Lohn & SV',
        'module.compliance' => 'Hinweisgebersystem',
        'module.isms' => 'ISMS',
        'module.finance' => 'Finanzschnittstelle',
    ],

    // Route-Namen-Muster → Modul-Code (zentrales Route-Gating durch
    // EnforcePlanModules). Erste passende Regel gewinnt. Nicht gelistete
    // Routen gelten als Core (immer erreichbar). Persoenliche Auswertungen
    // (reports.my-*, reports.work-balance, reports.attendance) bleiben Core.
    'routes' => [
        'kanban.*' => 'module.kanban',

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

        'customers.*' => 'module.vertrieb',
        'suppliers.*' => 'module.vertrieb',
        'projects.*' => 'module.vertrieb',
        'invoices.*' => 'module.vertrieb',
        'lexoffice.*' => 'module.vertrieb',
        'events.*' => 'module.vertrieb',
        'event-categories.*' => 'module.vertrieb',
        'materials.*' => 'module.vertrieb', // Abrechnungskatalog (Preis/Steuer/lexoffice/Billing)

        'assets.*' => 'module.fuhrpark',
        'vehicles.*' => 'module.fuhrpark',
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

        'form-templates.*' => 'module.forms',
        'form-submissions.*' => 'module.forms',

        'payroll.*' => 'module.lohn',
        'admin.surcharge-rules.*' => 'module.lohn', // Zuschlagsregeln (Feature 005)

        'whistleblowing.internal.*' => 'module.compliance',
        'whistleblowing.portal.*' => 'module.compliance',

        'isms.*' => 'module.isms',

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
        'module.vertrieb' => false,         // Rechnungen → GoBD / §147 AO (10 J.)
        'module.fuhrpark' => true,
        'module.liegenschaften' => true,
        'module.auswertungen_team' => true, // nur Auswertungen, keine Primaerdaten
        'module.chat' => true,
        'module.datenschutz' => false,      // VVT/Vorfaelle → Rechenschaft/Nachweis (Art. 5 Abs. 2)
        'module.documents' => false,        // Vertraege/Zertifikate/Nachweise → Aufbewahrungspflichten (GoBD/§147 AO)
        'module.knowledge' => true,         // org-eigenes Betriebswissen, keine gesetzliche Aufbewahrung
        'module.forms' => false,            // ausgefuellte Formulare koennen Nachweise sein (Pruef-/Abnahmeprotokolle)
        'module.lohn' => false,             // Lohn/SV → GoBD / §147 AO / SGB IV (6 J.)
        'module.compliance' => false,       // Hinweisgeber → HinSchG (3 J.)
        'module.isms' => false,             // Risikoregister/SoA → Compliance-Nachweise (Auditfähigkeit)
        'module.finance' => false,          // Übergabenachweise/Exportpakete → GoBD / §147 AO (10 J.)
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
