<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : help-center.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Themenbereiche des Hilfecenters (Feature 039, MVP-752).
 *
 * `sections`: Bereichs-Key → Material-Symbol + Topic-Muster (Str::is,
 * Reihenfolge relevant — der ERSTE Treffer gewinnt, wie in help-topics.php).
 * Titel/Beschreibung kommen aus lang/{locale}/help.php
 * (`help.sections.<key>.title` / `.description`) — hier steht bewusst kein
 * Anzeigetext. Topics ohne Treffer landen im Auffangbereich „weitere";
 * das Gate (HelpCenterCatalogTest) meldet sie, außer sie stehen in
 * `fallback_allowed`.
 */

return [

    'sections' => [
        'erste-schritte' => [
            'icon' => 'rocket_launch',
            'patterns' => [
                'dashboard.*', 'onboarding.*', 'auth.*', 'account.*',
                'navigation.*', 'search.*', 'workspaces.*', 'install.*',
                'offline.*', 'week.*', 'work.*', 'glossary*', 'help.*',
                'knowledge.*',
            ],
        ],
        'kunden-vertrieb' => [
            'icon' => 'groups',
            'patterns' => [
                'customers.*', 'customer.*', 'customer-portal.*',
                'foreign-customers*', 'contacts.*', 'projects.*', 'sales.*',
                'tenders.*', 'applications.*', 'communication.*',
                'circulars.*', 'appointments.*', 'events.*',
            ],
        ],
        'zeit-personal' => [
            'icon' => 'schedule',
            'patterns' => [
                'time-entries.*', 'time-accounts.*', 'attendance.*',
                'absences.*', 'overtime.*', 'corrections.*', 'timesheets.*',
                'presence.*', 'duties.*', 'planning.*', 'dispatch.*',
                'payroll.*', 'training.*', 'learning.*', 'location.*',
                'tours.*', 'travel-expenses.*',
            ],
        ],
        'auftraege-service' => [
            'icon' => 'assignment',
            'patterns' => [
                'diary-entries.*', 'protocols.*', 'procedures.*', 'forms.*',
                'agile.*', 'open-issues*', 'construction-notices*', 'boq.*',
                'permits.*', 'recipes.*', 'manufacturing.*', 'print.*',
                'patrols.*', 'helpdesk.*', 'sla.*', 'support.*', 'ideas.*',
                'claims.*', 'passenger.*',
            ],
        ],
        'material-lager' => [
            'icon' => 'warehouse',
            'patterns' => [
                'articles.*', 'catalog.*', 'supplier-catalogs.*',
                'supplier-scorecards.*', 'inventory.*', 'warehouses.*',
                'materials.*', 'procurement.*', 'products.*', 'pricing.*',
                'serials.*', 'disposal.*', 'rental.*', 'metering.*',
                'meter-readings*',
            ],
        ],
        'geraete-fuhrpark' => [
            'icon' => 'build',
            'patterns' => [
                'assets.*', 'asset-finance.*', 'asset-compliance.*',
                'fleet.*', 'facilities.*', 'key-handovers*', 'guarantees.*',
                'warranties.*', 'software.*', 'access.*',
            ],
        ],
        'faktura' => [
            'icon' => 'receipt_long',
            'patterns' => [
                'invoices.*', 'quotes.*', 'commissions*', 'contracts.*',
                'documents.*',
            ],
        ],
        'buchhaltung' => [
            'icon' => 'account_balance',
            'patterns' => [
                'accounting.*', 'finance.*', 'exports.*', 'investments.*',
            ],
        ],
        'auswertungen' => [
            'icon' => 'monitoring',
            'patterns' => [
                'reports.*',
            ],
        ],
        'sicherheit-compliance' => [
            'icon' => 'security',
            'patterns' => [
                'isms.*', 'privacy.*', 'whistleblowing.*', 'audit.*',
                'archive.*', 'crisis.*', 'safety.*', 'sustainability.*',
            ],
        ],
        'administration' => [
            'icon' => 'admin_panel_settings',
            'patterns' => [
                'admin.*', 'org.*', 'roles.*', 'scope.*', 'backup-targets.*',
                'cloud-intake.*', 'domains.*', 'legacy.*', 'ai.*',
            ],
        ],
    ],

    // Artikelschema der Pilotartikel (MVP-756): Topics mit Front-Matter
    // `schema: process` MÜSSEN diese sechs h2-Abschnitte in dieser
    // Reihenfolge tragen — Überschriften je Sprache in lang/{locale}/help.php
    // unter `schema.<key>`; Gate: HelpArticleSchemaTest.
    'article_schema' => [
        'zweck',
        'voraussetzungen',
        'ablauf',
        'beispiel',
        'fehler',
        'naechste-schritte',
    ],

    // Bildablage der Hilfeartikel (MVP-754): Basisdateien sprachneutral,
    // Locale-Override per Suffix `name.{locale}.{ext}`. Einzige Quelle für
    // Loader-Umschreibung, Auslieferung (HelpMediaController) und Gates.
    'media_path' => resource_path('help/media'),

    // Bewusst im Auffangbereich „Weitere Themen" (Gate-Ausnahmen):
    // external.participants ist die Hilfe zur externen Terminliste ohne
    // fachliche Heimat in den Kernbereichen.
    'fallback_allowed' => [
        'external.participants',
    ],

];
