<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : navigation_focus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Arbeitsbereiche — schaltbare, rein kosmetische Fokus-Ansichten (D13, wie
 * nav_hidden): Filter als letzter Schritt in {@see NavigationRegistry::build()},
 * schaltet nie etwas frei. Konzept: Feature 082 (WorkDiary-Architecture).
 *
 * Schema je Eintrag:
 *  - label / description : deutsche Quelltexte, per __() übersetzt.
 *  - icon                : Material-Symbols-Name.
 *  - keys                : sichtbar bleibende Sidebar-Schlüssel (Registry-Präfix
 *                          `section:`/`group:`/`item:`, wie nav_hidden). Gruppen-/
 *                          Item-Schlüssel zeigen nur den Ausschnitt, `null` = kein
 *                          Filter.
 *  - manage              : sichtbar bleibende Verwaltungsmenü-Routen; `null` =
 *                          unverändert (konservativer Default).
 * `all` = Voll-Umfang und Default für Bestandsnutzer (`keys = null`).
 */

return [
    // Reihenfolge = Anzeigereihenfolge im Großkachel-Dialog.
    'focuses' => [
        'time' => [
            'label' => 'Zeit & Alltag',
            'description' => 'Erfassen, planen, abrechnen — dein täglicher Arbeitsablauf.',
            'icon' => 'schedule',
            'keys' => [
                'section:work',
                'section:plan',
                'section:travel-expenses',
                'section:location',
                'group:reports-personal',
            ],
            'manage' => [
                'org.members.index', 'legacy.users.index', 'qualifications.index',
                'holidays.index', 'shift-types.index', 'event-categories.index',
            ],
        ],
        'sales' => [
            'label' => 'Projekte & Vertrieb',
            'description' => 'Kunden, Projekte, Angebote und Rechnungen im Blick.',
            'icon' => 'handshake',
            'keys' => [
                'group:sales-crm',
                'group:sales-recruiting',
                'group:sales-billing',
                'section:work',
                'group:reports-projects',
            ],
            'manage' => [
                'materials.index', 'tags.index', 'activity-categories.index',
                'org.members.index',
            ],
        ],
        'inventory' => [
            'label' => 'Lager & Fertigung',
            'description' => 'Artikel, Bestände, Fertigung und Bestellungen.',
            'icon' => 'inventory_2',
            'keys' => [
                'group:sales-inventory',
                'section:fleet',
                'group:reports-resources',
            ],
            'manage' => ['materials.index', 'tags.index'],
        ],
        'service' => [
            'label' => 'Service Desk',
            'description' => 'Tickets, Queues und SLAs für den Support.',
            'icon' => 'support_agent',
            'keys' => [
                'section:servicedesk',
                'section:work',
                'group:reports-team',
            ],
            'manage' => ['tags.index', 'activity-categories.index'],
        ],
        'facility' => [
            'label' => 'Facility Management',
            'description' => 'Standorte, Gebäude, Wartung und Objekte.',
            'icon' => 'apartment',
            'keys' => [
                'section:facility',
                'section:fleet',
                'section:plan',
                'section:asset-compliance',
                'section:archive',
            ],
            'manage' => ['holidays.index', 'shift-types.index', 'materials.index'],
        ],
        'finance' => [
            'label' => 'Buchhaltung & Finanzen',
            'description' => 'Rechnungen, DATEV/GoBD, Spesen-Freigabe und Auswertungen.',
            'icon' => 'account_balance',
            'keys' => [
                'group:sales-billing',
                'section:travel-expenses',
                'group:reports-finance',
                'section:archive',
            ],
            'manage' => null,
        ],
        'compliance' => [
            'label' => 'Compliance & Datenschutz',
            'description' => 'Meldestelle, Datenschutz, ISMS und Nachhaltigkeit.',
            'icon' => 'verified_user',
            'keys' => [
                'section:compliance',
                'section:datenschutz',
                'section:isms',
                'section:sustainability',
            ],
            'manage' => null,
        ],
        'all' => [
            'label' => 'Alles anzeigen',
            'description' => 'Der volle Funktionsumfang ohne Filter.',
            'icon' => 'apps',
            'keys' => null,
            'manage' => null,
        ],
    ],

    // Default, wenn weder Nutzer, Org noch Branchenprofil wählen (opt-in: Voll-Umfang).
    'default' => 'all',
];
