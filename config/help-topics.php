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

    'routes' => [
        // Tagesübersicht, Kanban und interne Kommunikation
        'today.show' => 'work.overview',
        'kanban.*' => 'work.overview',
        'chat.*' => 'communication.chat',

        // Arbeitsliste und Auftragsbuch
        'duties.index' => 'duties.overview',
        'diary.index' => 'diary-entries.create',
        'diary.create' => 'diary-entries.create',
        'diary.show' => 'diary-entries.edit',
        'diary.edit' => 'diary-entries.edit',

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

        // Hinweis: procedures.run hat (noch) keine eigene Seite — Prozedurläufe
        // finden in der Auftrags-Detailansicht statt und sind dort über den
        // manuellen <x-help-button topic="procedures.run"> erreichbar.

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
        'customers.*' => 'contacts.manage',
        'suppliers.*' => 'contacts.manage',
        'projects.*' => 'projects.manage',
        'invoices.*' => 'invoices.manage',
        'lexoffice.vouchers.*' => 'invoices.manage',
        'events.*' => 'events.manage',
        'assets.*' => 'assets.fleet',
        // SLA, Verträge & Service-Level (Feature 010): Tickets und Report
        // teilen sich das Überblick-Topic.
        'service-tickets.*' => 'sla.overview',
        'reports.sla' => 'sla.overview',
        'reports.sla.*' => 'sla.overview',
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

        // Finance & Zeitexport
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
        'admin.imports.*' => 'admin.import',
        'admin.security.*' => 'admin.security',
        'admin.components.*' => 'admin.security',
        'admin.support.report.*' => 'admin.support',
        'admin.backup.*' => 'admin.backups',
        'admin.branch-profiles.*' => 'admin.branch-profiles',

        // Hinweis: Das Dashboard hat bewusst KEINEN Auto-Kontext — der
        // sinnvolle Einstieg ist rollenabhängig (roles.*-Topics) und damit
        // nicht statisch über die Route auflösbar. Rollen-Einstiege sind
        // über related-Verweise und die Hilfe-Suche erreichbar.
    ],

];
