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

        // Wochenansicht der Aufträge sowie separate Zeiterfassung
        'week.index' => 'week.overview',
        'time-entries.create' => 'time-entries.start',
        'admin-time-entries.*' => 'time-entries.edit',
        'projects.time-entries.*' => 'time-entries.edit',
        'attendance.*' => 'attendance.manage',
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

        // Hinweis: procedures.run hat (noch) keine eigene Seite — Prozedurläufe
        // finden in der Auftrags-Detailansicht statt und sind dort über den
        // manuellen <x-help-button topic="procedures.run"> erreichbar.

        // ISMS (Feature 044/046): Anforderungen + SoA-Aussagen + Druckansicht
        // teilen sich ein Topic; isms.soa ist ein exakter Route-Name.
        'isms.requirements.*' => 'isms.requirements-soa',
        'isms.statements.*' => 'isms.requirements-soa',
        'isms.soa' => 'isms.requirements-soa',
        'isms.controls.*' => 'isms.controls',
        'isms.risks.*' => 'isms.risks',
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
        'vehicles.*' => 'assets.fleet',
        'energy-logs.*' => 'assets.fleet',
        'sites.*' => 'facilities.manage',
        'buildings.*' => 'facilities.manage',
        'floors.*' => 'facilities.manage',
        'rooms.*' => 'facilities.manage',
        'archive.*' => 'archive.manage',

        // Finance & Zeitexport
        'finance.transfers.*' => 'finance.transfers',
        'exports.*' => 'exports.payroll',

        // Administration (spezifische admin.*-Präfixe, kein breites admin.*)
        'admin.notification-rules.*' => 'admin.notification-rules',
        'admin.surcharge-rules.*' => 'admin.surcharge-rules',
        'admin.organizations.*' => 'admin.tenants',
        'admin.access.*' => 'admin.roles',
        'admin.license.*' => 'admin.license',
        'admin.imports.*' => 'admin.import',
        'admin.components.*' => 'admin.security',

        // Hinweis: Das Dashboard hat bewusst KEINEN Auto-Kontext — der
        // sinnvolle Einstieg ist rollenabhängig (roles.*-Topics) und damit
        // nicht statisch über die Route auflösbar. Rollen-Einstiege sind
        // über related-Verweise und die Hilfe-Suche erreichbar.
    ],

];
