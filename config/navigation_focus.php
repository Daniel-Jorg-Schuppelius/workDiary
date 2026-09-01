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
                // Branchenprofil Taxi/Mietwagen (MVP-456): Personenbefoerderung
                // IST der Arbeitsalltag, wo das Profil aktiv ist.
                'section:passenger',
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
                'group:sales-billing',
                'section:work',
                'section:claims',
                // Branchenprofil Druck-/Kopiershop (MVP-459): Druckauftraege
                // sind Auftraege und gehoeren in denselben Blick.
                'section:print',
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
                'group:sales-procurement',
                'group:sales-manufacturing',
                'section:fleet',
                // Vermietung bewegt denselben Bestand (Feature 073).
                'section:rental',
                // Entsorgung ist das Ende derselben Materialkette.
                'section:disposal',
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
                // Reklamationen sind die kaufmaennische Fortsetzung eines
                // Servicefalls (Feature 072) — wer den Desk fuehrt, braucht sie.
                'section:claims',
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
                // Entsorgung faellt am Objekt an, nicht im Lager.
                'section:disposal',
                // Domains sind Betriebsmittel mit Laufzeit und Frist
                // (Feature 083) — dieselbe Pflege wie Objekte.
                'section:domains',
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
                'group:sales-payments',
                'group:sales-accounting',
                'section:travel-expenses',
                // Anlagenregister und AfA sind Buchhaltung (Feature 133).
                'section:asset-finance',
                // Vertraege tragen Fristen, Werte und Kuendigungen.
                'section:contracts',
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
                // Krisenmanagement (Feature 070) ist der Ernstfall derselben
                // Vorsorge — im Ernstfall will niemand den Bereich wechseln.
                'section:crisis',
                'section:contracts',
            ],
            'manage' => null,
        ],
        // Personal & Qualifikation (2026-09-01): Arbeitsschutz und
        // Lernplattform gehoeren fachlich zusammen — Feature 132/145 fuehren
        // das Pflicht-Soll, Feature 149 die Durchfuehrung. Ohne eigenen
        // Bereich lagen beide in KEINEM Arbeitsbereich und verschwanden fuer
        // jeden, der einen Fokus gewaehlt hatte.
        'people' => [
            'label' => 'Personal & Qualifikation',
            'description' => 'Unterweisungen, Schulungen und Nachweise deiner Leute.',
            'icon' => 'school',
            'keys' => [
                'section:safety',
                'section:learning',
                'section:work',
                'group:reports-personal',
            ],
            'manage' => [
                'org.members.index', 'qualifications.index',
            ],
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
