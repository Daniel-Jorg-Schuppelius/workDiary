<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : help-topics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Kontextbezogene Prozesshilfe (Feature 039): Map von Route-Namen-Mustern auf
 * Hilfe-Topic-Codes (resources/help/{locale}/{topic}.md). Wildcards wie bei
 * NavGate/FeatureFlagResolver über Str::is. Reihenfolge ist relevant: der
 * ERSTE Treffer gewinnt — spezifische Muster daher vor breiten Wildcards
 * eintragen. Exakte Route-Namen werden vor den Mustern geprüft.
 *
 * Sichtbarkeit (audience-Filter) wird NICHT hier, sondern serverseitig über
 * HelpContextResolver::visibleTopicFor() + HelpTopicResolver geprüft.
 */

return [

    // Bewusste Ausnahmen der Abdeckungsprüfung (composer help:coverage):
    // öffentliche Seiten ohne App-Layout + die Hilfeseite selbst.
    'coverage_exceptions' => [
        'help.topics.show',
        'external.show',
        'quotes.portal.show',
        // Öffentliche Bewerber-Karriereseiten (sessionlos, externe Zielgruppe).
        'careers.index',
        'careers.show',
        // Externer B2B-Katalogzugang (Feature 099): tokengesicherte Public-Routen.
        'b2b-catalog.index',
        'b2b-catalog.show',
    ],

    'routes' => [
        // Oberflächen-Konfiguration (Feature 081): Funktionsumfang,
        // Menüanpassung und Funktionskatalog.
        'admin.scope.*' => 'scope.overview',
        // Arbeitsbereiche — schaltbare Fokus-Ansichten (Feature 082)
        'admin.workspaces.*' => 'workspaces.overview',
        'me.navigation.*' => 'navigation.customize',
        'me.functions' => 'navigation.customize',
        // PDF-Dokumentdesign / Firmenbogen (Feature 076, Phase 28)
        'admin.document-design.*' => 'admin.document-design',
        // Schreibfehler-Wörterbuch für Positionstexte
        'admin.text-corrections.*' => 'admin.text-corrections',
        // orgaMAX-Buchhaltung-Plugin (Feature 077, Phase 29)
        'admin.orgamax.*' => 'admin.orgamax',
        // ── Nachträge 2026-07-10: Phasen 19–24 + Lücken-Sweep ──────────
        // Bewerbungen/Ausschreibungen (Feature 068)
        'tenders.*' => 'applications.overview',
        'recruiting.*' => 'applications.overview',
        'applications.report' => 'applications.overview',
        // Reklamation/Gewährleistung (Feature 072)
        'claims.*' => 'claims.overview',
        // Menü-/Buffetplanung Partyservice (MVP-455)
        'recipe-menus.*' => 'recipes.menus',
        // Plugin-Admin-Seiten ohne eigenes Topic → generische Integrationen.
        'admin.calendly.*' => 'admin.integrations',
        'admin.fritzbox.*' => 'admin.integrations',
        // Globale Suche
        'search.*' => 'search.overview',
        // Security-Event-Log (Feature 096) → bestehendes Sicherheits-Topic.
        'admin.security-events.*' => 'admin.security',
        // Personenbeförderung Taxi/Mietwagen (MVP-456)
        'passenger-rides.*' => 'passenger.overview',
        'passenger-masterdata.*' => 'passenger.overview',
        'passenger-settlements.*' => 'passenger.overview',
        // Druckerzeugnisse/Kopiershop (MVP-459)
        'print-orders.*' => 'print.orders',

        'contracts.*' => 'contracts.overview',
        // Cloud-Dokumenteingang (Feature 080).
        'admin.cloud-intake.*' => 'cloud-intake.overview',
        // Cloud-Backupziele (Feature 017 Phase 32).
        'admin.backup-targets.*' => 'backup-targets.overview',
        // Produktstamm (Typ-Ebene, MVP-354).
        'products.*' => 'products.overview',
        'supplier-scorecards.*' => 'supplier-scorecards.overview',
        // Domainverwaltung / DomainReselling (Feature 083, module.domain):
        // Admin richtet die Provider-Verbindung ein; das Modul verwaltet
        // Portfolio, DNS, Laufzeiten und Reseller/Subuser (ein Topic).
        'admin.domain-provider.*' => 'admin.domain-provider',
        'domains.*' => 'domains.overview',
        'domain-reseller.*' => 'domains.overview',
        // Offline-Sync (Feature 035, MVP-351): Offline-Änderungs-Seite.
        'offline.*' => 'offline.changes',
        // Geräte-/Maschinenverleih (Feature 073)
        'rental.*' => 'rental.overview',
        // Entsorgungsakte (Feature 100, module.entsorgung)
        'disposal.*' => 'disposal.overview',
        // Leasing/Finanzierung/Asset-Verträge (Feature 074)
        'asset-finance.*' => 'asset-finance.overview',
        // Prüfmittel/Eichung/Kalibrierung (Feature 075)
        'asset-compliance.*' => 'asset-compliance.overview',
        // Krisenmanagement (Feature 070)
        'crisis.*' => 'crisis.overview',
        // Investitionsplanung (Feature 069)
        'investments.*' => 'investments.overview',
        // Nachhaltigkeit/ESG (Feature 071)
        'sustainability.*' => 'sustainability.overview',
        // Angebote (Feature 066)
        'quotes.index' => 'quotes.overview',
        'quotes.show' => 'quotes.overview',
        // Steuerregelmatrix (Phase 23) + E-Rechnungs-Eingang + GoBD
        'finance.tax-rules.*' => 'finance.tax-rules',
        'finance.incoming-invoices.*' => 'finance.incoming-invoices',
        'finance.gobd.*' => 'finance.gobd',
        // Agiles PM (Feature 064)
        'agile.*' => 'agile.overview',
        // GAEB-Leistungsverzeichnisse (Feature 049)
        'bill-of-quantities.*' => 'boq.overview',
        // Lieferantenkataloge (Feature 050) + Preis-/Margenregeln
        'supplier-catalogs.*' => 'supplier-catalogs.overview',
        'pricing-margin-rules.*' => 'pricing.margin-rules',
        // Ideenlandkarten (Feature 054)
        'ideas.*' => 'ideas.overview',
        // Genehmigungs-Register
        'permits.*' => 'permits.overview',
        // Standortbasierte Zeiterfassung (Geofences/Geräte/Review)
        'geofences.*' => 'location.overview',
        'location.*' => 'location.overview',
        // Helpdesk-Verwaltung (Feature 065): Board/Queues/Routing/Berichte
        'helpdesk.board.*' => 'helpdesk.overview',
        'helpdesk.queues.*' => 'helpdesk.overview',
        'helpdesk.routing.*' => 'helpdesk.overview',
        'helpdesk.reports.*' => 'helpdesk.overview',
        // Servicekatalog + Genehmigungs-Inbox (Feature 065, MVP-154):
        // bewusst KEIN neues Topic (Paritätspflicht ×alle Locales).
        'servicedesk.catalog.*' => 'helpdesk.overview',
        'servicedesk.approvals.*' => 'helpdesk.overview',
        // Problem-Management (Feature 065, MVP-156): gleiches Topic.
        'servicedesk.problems.*' => 'helpdesk.overview',
        // Change-/CAB-Management (Feature 065, MVP-157): gleiches Topic.
        'servicedesk.changes.*' => 'helpdesk.overview',
        'servicedesk.change-templates.*' => 'helpdesk.overview',
        // Externe Bestands-Outbox (E1): Konfliktliste
        'inventory.conflicts.*' => 'inventory.conflicts',
        // Externe Kontakte = externe Protokoll-Teilnehmer
        'external-contacts.*' => 'external.participants',
        // SLA-Verträge
        'sla-contracts.*' => 'sla.overview',
        // Verfahrenslauf-Detail (Designer/Läufe sind bereits gemappt)
        'procedure-runs.show' => 'procedures.run',
        // ISMS-Auditprogramme → bestehendes Audit-Topic
        'isms.audit-programs.*' => 'isms.audits',
        // Startseite → Dashboard-Hilfe
        'home' => 'dashboard.overview',
        // Admin: Integrations-Verwaltung (ein gemeinsames Topic)
        'admin.caldav.*' => 'admin.integrations',
        'admin.carddav.*' => 'admin.integrations',
        'admin.clockify.*' => 'admin.integrations',
        'admin.cti.*' => 'admin.integrations',
        'admin.google-calendar.*' => 'admin.integrations',
        'admin.msgraph.*' => 'admin.integrations',
        'admin.sharepoint.*' => 'admin.integrations',
        'admin.jtl.*' => 'admin.jtl-wawi',
        'admin.billbee.*' => 'admin.billbee',
        'admin.kimai.*' => 'admin.integrations',
        'admin.mail.*' => 'admin.integrations',
        'admin.todoist.*' => 'admin.integrations',
        'admin.webdav.*' => 'admin.integrations',
        'admin.zammad.*' => 'admin.integrations',
        'admin.chat.*' => 'admin.integrations',
        'admin.terminals.*' => 'admin.integrations',
        'admin.shipments.*' => 'admin.integrations',
        'admin.sso.*' => 'admin.sso',
        'admin.integration.mappings.*' => 'admin.integrations',
        // Admin: Betrieb (Betriebsaufgaben, Scheduler, Wartungsfenster)
        'admin.operations.*' => 'admin.operations',
        'admin.scheduler.*' => 'admin.scheduler',
        'admin.ai.*' => 'ai.services', // Feature 025 KI-Dienste + Gedächtnis
        'admin.maintenance-windows.*' => 'admin.operations',
        // Admin: Systemeinstellungen, Datenhoheit, Kostenstellenregeln
        'admin.settings.*' => 'admin.settings',
        'admin.data-ownership.*' => 'admin.data-ownership',
        'admin.cost-center-rules.*' => 'admin.cost-center-rules',
        // Lohnarten-Mapping + Export-Lieferung (A21 · MVP-019): Teil des
        // Zeit-Export-Prozesses, gleicher Hilfetext wie exports.*.
        'admin.wage-type-mappings.*' => 'exports.payroll',
        // Fernwartungszugriffe → bestehendes Remote-Support-Topic
        'admin.support.grants.*' => 'admin.remote-support',

        // Fehlermeldesystem (Feature 041, MVP-053)
        'problem-reports.*' => 'support.report-problem',
        'admin.problem-reports.*' => 'support.report-problem',

        // Tagesübersicht, Kanban und interne Kommunikation
        'today.show' => 'work.overview',
        'kanban.*' => 'work.overview',
        'tasks.global.*' => 'work.overview',
        'chat.*' => 'communication.chat',

        // Arbeitsliste und Auftragsbuch
        'duties.index' => 'duties.overview',
        // Notfall-/Bereitschaftseinsätze münden in die Arbeitsliste.
        'assignments.*' => 'duties.overview',
        'diary.index' => 'diary-entries.create',
        'diary.create' => 'diary-entries.create',
        'diary.show' => 'diary-entries.edit',
        'diary.edit' => 'diary-entries.edit',
        'diary.case-file' => 'diary-entries.edit',

        // Leitstelle (Feature 029): Dispatch-Board + Karten-Sicht. Vor dem
        // breiten dispatch.*-Muster, weil eigenes Topic.
        'dispatch.board' => 'dispatch.board',
        'dispatch.map' => 'dispatch.board',

        // Disposition / Einsatzplanung (Feature 028)
        'dispatch.*' => 'dispatch.overview',
        'vehicle-reservations.*' => 'dispatch.overview',

        // Wochenansicht der Aufträge sowie separate Zeiterfassung
        'week.index' => 'week.overview',
        'time-entries.create' => 'time-entries.start',
        'stopwatch.*' => 'time-entries.start',
        'admin-time-entries.*' => 'time-entries.edit',
        'projects.time-entries.*' => 'time-entries.edit',
        'attendance.*' => 'attendance.manage',
        'day-close.*' => 'time-entries.day-close',
        'timesheets.*' => 'timesheets.manage',
        'projects.timesheets.*' => 'timesheets.manage',
        'flex.*' => 'time-accounts.flex',
        'users.flex-eligibility.*' => 'time-accounts.flex',
        'month-approval.*' => 'time-accounts.flex',
        'admin.month-approval.*' => 'time-accounts.flex',
        'admin.corrections.*' => 'time-accounts.flex',

        // Persönliche Kontosicherheit
        'account.2fa.*' => 'account.two-factor',

        // Planung, Reisen und Abwesenheiten
        'duty-plans.*' => 'planning.shifts',
        // Dienstplan-Intelligenz (Feature 007) – vor dem breiten schedule.*
        'schedule.availability.*' => 'planning.availability',
        'schedule.exchanges.*' => 'planning.exchange',
        'schedule.*' => 'planning.shifts',
        'scheduled-shifts.*' => 'planning.shifts',
        'shift-types.*' => 'planning.shifts',
        'shifts.*' => 'planning.shifts',
        'tours.*' => 'tours.manage',
        'travel-logs.*' => 'travel-expenses.manage',
        'expenses.*' => 'travel-expenses.manage',
        'expense-approvals.*' => 'travel-expenses.manage',
        'per-diem-trips.*' => 'travel-expenses.manage',
        // Urlaubskonto (Phase 38, MVP-413)
        'vacation-entitlements.*' => 'absences.entitlements',
        'vacations.*' => 'absences.manage',
        'sick-leaves.*' => 'absences.manage',

        // Auswertungen (Drilldowns vor den breiten Berichts-Routen)
        'reports.*.drilldown.*' => 'reports.drilldown',
        'reports.arbzg-compliance' => 'reports.arbzg-compliance',
        'reports.economics' => 'reports.economics',
        'reports.cohort-comparison' => 'reports.cohort-comparison',
        'reports.customers' => 'reports.customer-analysis',
        'reports.customer-project' => 'reports.customer-analysis',
        'reports.entry-types' => 'reports.entry-type-analysis',
        // Entscheidungsanalysen (Phase 53, MVP-465–468).
        'reports.customer-value' => 'reports.customer-value',
        'reports.customer-retention' => 'reports.customer-retention',
        'reports.utilization' => 'reports.utilization',
        'reports.payment-behavior' => 'reports.payment-behavior',
        'reports.suppliers' => 'reports.supplier-analysis',
        'reports.*' => 'reports.overview',

        // Onboarding
        'onboarding.*' => 'onboarding.checklist',

        // Protokolle (Signatur-Routen vor dem breiten Muster)
        'protocols.public-sign*' => 'protocols.sign',
        'protocols.signature-tokens.*' => 'protocols.sign',
        'protocols.*' => 'protocols.create',

        // Kundenportal & Freigaben (Feature 012): interne Rückfragen-Liste.
        'customer-queries.*' => 'customer.queries',

        // Prozedurvorlagen-Designer (Feature 026): Listenseite + Editor.
        'procedures.*' => 'procedures.designer',

        // procedures.run hat keine eigene Seite — Läufe stehen in der Auftrags-
        // Detailansicht via manuellem <x-help-button topic="procedures.run">.

        // Sicherheitsereignis-Register (Arbeitsschutz, Feature 013)
        'safety-events.*' => 'safety.overview',

        // ISMS (Feature 044/046): Anforderungen + SoA-Aussagen + Druckansicht
        // teilen sich ein Topic; isms.soa/isms.dashboard sind exakte
        // Route-Namen (kein isms.*-Catch-all vorhanden — Mapping nötig).
        'isms.dashboard' => 'isms.overview',
        // Reifegrad-/Readiness-Assessment (Feature 044, MVP 3): eigenes Topic.
        'isms.readiness' => 'isms.readiness',
        // Lieferantenbewertung (Feature 044, MVP 2/3): eigenes Topic.
        'isms.suppliers.*' => 'isms.suppliers',
        'isms.requirements.*' => 'isms.requirements-soa',
        'isms.statements.*' => 'isms.requirements-soa',
        'isms.soa' => 'isms.requirements-soa',
        'isms.controls.*' => 'isms.controls',
        'isms.risks.*' => 'isms.risks',
        // Betrieb und Wirksamkeit (Feature 044, MVP 2): Vorfälle teilen sich
        // ein Topic; Schwachstellen und Advisories teilen sich eines.
        'isms.incidents.*' => 'isms.incidents',
        'isms.vulnerabilities.*' => 'isms.vulnerabilities',
        'isms.advisories.*' => 'isms.vulnerabilities',
        'isms.audits.*' => 'isms.audits',
        // Managementbewertungen werden im Audits-Topic miterklärt.
        'isms.reviews.*' => 'isms.audits',
        'isms.conformity.*' => 'isms.conformity',
        'isms.packages.*' => 'isms.packages',
        'isms.software.*' => 'isms.software',
        'isms.scopes.*' => 'isms.overview',

        // Datenschutz (Modul in Weiterentwicklung → bewusst nur Überblick)
        'dataprotection.*' => 'privacy.overview',

        // Dokumente, Formulare, Wissensbasis, Kommunikationsnotizen
        'documents.*' => 'documents.manage',
        'form-templates.*' => 'forms.templates',
        'form-submissions.*' => 'forms.fill',
        'knowledge.*' => 'knowledge.articles',
        'communication-notes.*' => 'communication.notes',

        // Stammdaten und operative Fachmodule
        // Sonderkonditionen & Abrechnungskonto (Feature 098) — VOR customers.*.
        'customers.billing.*' => 'customers.billing',
        'customers.*' => 'contacts.manage',
        'suppliers.*' => 'contacts.manage',
        'projects.*' => 'projects.manage',
        // Wiederkehrende Rechnungen (Phase 38, MVP-415)
        'invoice-schedules.*' => 'invoices.schedules',
        // Kassenbuch (Phase 38, MVP-414)
        'cash-registers.*' => 'finance.cashbook',
        'invoices.*' => 'invoices.manage',
        'invoice-templates.*' => 'invoices.manage',
        'lexoffice.vouchers.*' => 'invoices.manage',
        'events.*' => 'events.manage',
        'assets.*' => 'assets.fleet',
        // SLA, Verträge & Service-Level (Feature 010): Tickets und Report
        // teilen sich das Überblick-Topic.
        'service-tickets.*' => 'sla.overview',
        'reports.sla' => 'sla.overview',
        'reports.sla.*' => 'sla.overview',
        // Führerscheinkontrolle (Phase 38, MVP-417)
        'driver-license-checks.*' => 'fleet.license-checks',
        'vehicles.*' => 'assets.fleet',
        'energy-logs.*' => 'assets.fleet',
        'sites.*' => 'facilities.manage',
        'buildings.*' => 'facilities.manage',
        'floors.*' => 'facilities.manage',
        'rooms.*' => 'facilities.manage',
        'archive.*' => 'archive.manage',

        // Externe Beteiligte (Feature 033): Einladungen verwalten.
        'external.create' => 'external.participants',
        'external.store' => 'external.participants',

        // Warenwirtschaft – Artikel & Lager (Feature 060/066)
        'articles.*' => 'articles.master',
        'lexoffice.articles.*' => 'articles.lexoffice',
        'materials.*' => 'materials.manage',
        'inventory.stock' => 'inventory.stock',
        'inventory.lots' => 'inventory.stock',
        'inventory.scan' => 'inventory.stock',
        'inventory.counts.*' => 'inventory.counts',
        'inventory.label-templates.*' => 'inventory.labels',
        'inventory.labels.*' => 'inventory.labels',
        'warehouses.*' => 'warehouses.manage',

        // Warenwirtschaft – Fertigung, Beschaffung, Seriennummern
        'manufacturing-orders.*' => 'manufacturing.orders',
        'manufacturing-planning.*' => 'manufacturing.orders',
        'work-centers.*' => 'manufacturing.work-centers',
        'purchase-orders.*' => 'procurement.orders',
        // serials.public-passport (öffentliche Token-Seite) hat kein
        // App-Chrome, das breite Muster schadet dort aber nicht.
        'serials.*' => 'serials.tracking',

        // Stammdaten & Kataloge
        'activity-categories.*' => 'catalog.activity-categories',
        'event-categories.*' => 'catalog.event-categories',
        'admin.entry-types.*' => 'catalog.entry-types',
        'admin.expense-categories.*' => 'catalog.expense-categories',
        'admin.per-diem-rates.*' => 'catalog.per-diem-rates',
        'tags.*' => 'catalog.tags',
        'holidays.*' => 'catalog.holidays',
        'qualifications.*' => 'catalog.qualifications',
        'admin.number-formats.*' => 'admin.number-formats',
        // Klassifikationen und ihre Pflichtregeln teilen sich ein Topic
        // (disjunkte Muster, da "classifications." ≠ "classification-").
        'admin.classifications.*' => 'admin.classifications',
        'admin.classification-requirements.*' => 'admin.classifications',

        // Personal & operative Module
        'org.members.*' => 'org.members',
        'users.work-schedule.*' => 'org.members',
        'teams.*' => 'org.teams',
        'payroll.*' => 'payroll.overview',
        'corrections.*' => 'corrections.requests',
        'key-handovers.*' => 'key-handovers',
        'meter-readings.*' => 'meter-readings',
        'foreign-customers.*' => 'foreign-customers',
        'open-issues.*' => 'open-issues',
        // Eigenständiges Software-/Lizenzinventar (NICHT isms.software).
        'software.*' => 'software.inventory',

        // Compliance – Hinweisgebersystem & Audit
        'whistleblowing.internal.*' => 'whistleblowing.cases',
        'whistleblowing.portal.edit' => 'whistleblowing.portal',
        'whistleblowing.portal' => 'whistleblowing.report',
        'whistleblowing.receipt' => 'whistleblowing.report',
        'whistleblowing.mailbox.*' => 'whistleblowing.report',
        'audit.*' => 'audit.log',

        // Finance & Zeitexport
        'finance.open-times.*' => 'finance.open-times',
        'finance.transfers.*' => 'finance.transfers',
        'finance.reconciliation.*' => 'finance.reconciliation',
        'finance.bank-accounts.*' => 'finance.reconciliation',
        'finance.datev.*' => 'finance.datev-bookings',
        'exports.*' => 'exports.payroll',

        // Administration (spezifische admin.*-Präfixe, kein breites admin.*)
        'admin.notification-rules.*' => 'admin.notification-rules',
        'admin.webhooks.*' => 'admin.webhooks',
        'admin.surcharge-rules.*' => 'admin.surcharge-rules',
        'admin.organizations.*' => 'admin.tenants',
        'admin.access.*' => 'admin.roles',
        'admin.license.*' => 'admin.license',
        'license.show' => 'admin.license',
        'admin.imports.*' => 'admin.import',
        'admin.security.*' => 'admin.security',
        // Quelltext-Integrität (Feature 095): eigene Seite, eigenes Topic.
        'admin.integrity.*' => 'admin.integrity',
        'admin.sessions.*' => 'admin.security', // Sitzungs-/Token-Verwaltung = Sicherheitsseite
        'admin.components.*' => 'admin.security',
        'admin.support.report.*' => 'admin.support',
        'admin.support.access-audit.*' => 'admin.support',
        'admin.backup.*' => 'admin.backups',
        'admin.branch-profiles.*' => 'admin.branch-profiles',

        // Administration – Betrieb, Stammdaten-Pflege & Integrationen
        'admin.automations.*' => 'admin.automations',
        'admin.branding.*' => 'admin.branding',
        'admin.data.*' => 'admin.data-transfer',
        'admin.legacy-migration.*' => 'admin.legacy-migration',
        'admin.demo.*' => 'admin.demo-data',
        'admin.diagnostics.*' => 'admin.diagnostics',
        'admin.metrics.*' => 'admin.metrics',
        'admin.invoice-mail-templates.*' => 'admin.invoice-mail-templates',
        'admin.plugins.*' => 'admin.plugins',
        'admin.plugin-errors.*' => 'admin.plugins',
        'admin.privacy.*' => 'admin.privacy-tools',
        'admin.remote-support.*' => 'admin.remote-support',
        'admin.report-targets.*' => 'admin.report-targets',
        'admin.themes.*' => 'admin.themes',
        'admin.toggl.*' => 'admin.toggl',
        'admin.openproject.*' => 'admin.openproject',
        'admin.lexoffice.*' => 'admin.lexoffice',

        // Persönliches Konto & Dashboard. Das Dashboard erklärt jetzt die
        // (rollenabhängig gefüllten) Widgets selbst; rollenspezifische
        // Einstiege bleiben zusätzlich über related/Suche erreichbar.
        'dashboard' => 'dashboard.overview',
        'dashboard.customize' => 'dashboard.overview',
        // account.2fa.* ist weiter oben bereits auf account.two-factor
        // gemappt; die folgenden account.*-Muster sind dazu disjunkt.
        'account.profile.*' => 'account.profile',
        'account.password.*' => 'account.profile',
        'account.work-schedule' => 'account.profile',
        'account.calendar.*' => 'account.profile',
        'notifications.*' => 'account.notifications',
        'bookmarks.*' => 'account.bookmarks',
        'filter-presets.*' => 'account.bookmarks',
        'profile.api-tokens.*' => 'account.api-tokens',
        'calendar.index' => 'account.calendar',

        // Kundenportal (eigener customer-Guard, eigene Zielgruppe)
        'customer.dashboard' => 'customer-portal.overview',
        'customer.diary.*' => 'customer-portal.diary',
        'customer.invoices.*' => 'customer-portal.invoices',
        'customer.billing.*' => 'customer-portal.billing',
        'customer.open-issues.*' => 'customer-portal.issues',
        'customer.time-entries.*' => 'customer-portal.time',
        'customer.login' => 'customer-portal.access',
        'customer.2fa.*' => 'customer-portal.access',
        'customer.two-factor.*' => 'customer-portal.access',

        // Anmeldung, Registrierung, Setup-Assistent und Altsystem-Brücke
        'login' => 'auth.login',
        'register' => 'auth.register',
        'password.request' => 'auth.password-reset',
        'password.reset' => 'auth.password-reset',
        'two-factor.login' => 'auth.two-factor',
        'install.*' => 'install.wizard',
        'legacy.*' => 'legacy.overview',
    ],

];
