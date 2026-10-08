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
        'verein' => [
            'icon' => 'groups',
            'patterns' => ['club.*'],
        ],
        'auftraege-service' => [
            'icon' => 'assignment',
            'patterns' => [
                'diary-entries.*', 'protocols.*', 'procedures.*', 'forms.*',
                'agile.*', 'open-issues*', 'construction-notices*', 'boq.*', 'takeoffs',
                'permits.*', 'recipes.*', 'manufacturing.*', 'print.*',
                'patrols.*', 'helpdesk.*', 'sla.*', 'support.*', 'ideas.*',
                'claims.*', 'passenger.*', 'damage-cases.*',
            ],
        ],
        'material-lager' => [
            'icon' => 'warehouse',
            'patterns' => [
                'articles.*', 'catalog.*', 'supplier-catalogs.*',
                'supplier-scorecards.*', 'inventory.*', 'warehouses.*',
                'materials.*', 'procurement.*', 'products.*', 'pricing.*',
                'serials.*', 'disposal.*', 'rental.*', 'metering.*',
                'meter-readings*', 'recalls.*', 'supplier-questionnaires.*',
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
                'invoices.*', 'quotes.*', 'billing.*', 'commissions*', 'contracts.*',
                // Lexware-Office-Tarifergänzungen (Feature 158).
                'lexware.*',
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

    // Füllwörter der Hilfesuche (MVP-1079), ASCII-gefaltet, ergänzend zu
    // `search.stopwords` (Deutsch und Englisch der Tätigkeitssuche). Sie
    // fallen nur, solange ein Inhaltswort bleibt.
    'stopwords' => [
        'en' => ['how', 'do', 'does', 'can', 'could', 'i', 'my', 'me', 'is', 'are', 'a', 'an', 'to', 'of', 'in', 'on', 'it', 'this', 'that', 'there', 'which', 'why', 'want', 'find', 'turn', 'please'],
        'fr' => ['comment', 'ou', 'quoi', 'quel', 'quelle', 'je', 'j', 'me', 'mon', 'ma', 'mes', 'le', 'la', 'les', 'l', 'un', 'une', 'des', 'de', 'du', 'd', 'et', 'est', 'a', 'au', 'aux', 'en', 'dans', 'pour', 'sur', 'avec', 'peut', 'puis', 'faire', 'trouve', 'trouver'],
        'it' => ['come', 'dove', 'cosa', 'quale', 'io', 'mi', 'il', 'lo', 'la', 'i', 'gli', 'le', 'l', 'un', 'una', 'uno', 'di', 'del', 'della', 'dei', 'e', 'o', 'a', 'al', 'alla', 'da', 'in', 'nel', 'per', 'su', 'con', 'posso', 'si', 'trovo', 'trovare'],
        'es' => ['como', 'donde', 'que', 'cual', 'yo', 'me', 'mi', 'el', 'la', 'los', 'las', 'un', 'una', 'de', 'del', 'y', 'o', 'a', 'al', 'en', 'para', 'por', 'con', 'puedo', 'se', 'encuentro', 'encontrar'],
    ],

    // Bewusst im Auffangbereich „Weitere Themen" (Gate-Ausnahmen):
    // external.participants ist die Hilfe zur externen Terminliste ohne
    // fachliche Heimat in den Kernbereichen.
    'fallback_allowed' => [
        'external.participants',
    ],

];
