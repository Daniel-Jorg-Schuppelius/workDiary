<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Arbeitsbereiche — schaltbare Fokus-Ansichten (Feature 082).
    'focus' => [
        'admin' => [
            'title' => 'Arbeitsbereiche',
            'subtitle' => 'Lege fest, welche Arbeitsbereiche deine Organisation im Umschalter anbietet, benenne sie um und wähle einen Standard.',
            'hint' => 'Nur ein Vorschlag: der Standard-Arbeitsbereich wird niemandem aufgezwungen — jede Person kann jederzeit wechseln. Ausblenden ändert keine Rechte.',
            'list_heading' => 'Angebotene Arbeitsbereiche',
            'configured_at' => 'Zuletzt festgelegt: :date',
            'mandatory' => 'immer verfügbar',
            'is_default' => 'Standard',
            'rename' => 'Anzeigename',
            'offered' => 'angeboten',
            'set_default' => 'Standard',
            'saved' => 'Arbeitsbereiche gespeichert.',
        ],
        'switcher' => 'Arbeitsbereich wechseln',
        'eyebrow' => 'Arbeitsbereich',
        'all' => 'Alles anzeigen',
        'active' => 'Aktiv',
        'reveal' => 'Alle anzeigen',
        'reveal_off' => 'Nur Fokus zeigen',
        'dialog' => [
            'eyebrow' => 'Ansicht fokussieren',
            'title' => 'Womit arbeitest du gerade?',
            'subtitle' => 'Wähle einen Arbeitsbereich — die Navigation zeigt dann nur die passenden Bereiche. Nichts wird gelöscht oder gesperrt; du kannst jederzeit wechseln.',
            'footnote' => 'Ausgeblendete Bereiche bleiben über die globale Suche und „Alle anzeigen“ erreichbar.',
        ],
        'flash' => [
            'unknown' => 'Unbekannter Arbeitsbereich.',
            'switched' => 'Arbeitsbereich „:name“ aktiv.',
        ],
    ],
    'title' => [
        'index' => 'Funktionsumfang',
    ],
    'nav' => [
        'customize' => 'Menü anpassen',
        'functions' => 'Alle Funktionen',
    ],
    'page' => [
        'subtitle' => 'Sichtbaren Funktionsumfang der Organisation festlegen: Presets für den schnellen Start oder Module einzeln an- und abschalten.',
        'no_data_loss' => 'Das Deaktivieren blendet Module nur aus und sperrt ihre Seiten — es werden keine Daten gelöscht. Beim Reaktivieren ist alles wieder da.',
    ],
    'presets' => [
        'heading' => 'Presets',
        'hint' => 'Ein Preset ist eine Schreibhilfe: Es schaltet die Modul-Liste unten in einem Schritt. Danach kannst du einzeln nachjustieren.',
        'apply' => 'Preset „:preset“ anwenden',
        'all_modules' => 'Alle lizenzierten Module',
        'module_count' => '{1} :count Zusatzmodul|[2,*] :count Zusatzmodule',
    ],
    'recommendation' => [
        'heading' => 'Empfehlung aus dem Branchenprofil',
        'hint' => 'Das installierte Branchenprofil „:profile“ empfiehlt die folgenden Module.',
        'apply' => 'Empfehlung übernehmen',
    ],
    'modules' => [
        'heading' => 'Module einzeln festlegen',
        'configured_at' => 'Zuletzt festgelegt: :date',
        'not_licensed_hint' => 'Im aktuellen Tarif nicht enthalten — über die Lizenzverwaltung erweiterbar.',
    ],
    'flash' => [
        'saved' => 'Funktionsumfang gespeichert (:disabled deaktiviert, :enabled aktiviert). Es wurden keine Daten gelöscht.',
        'no_recommendation' => 'Für diese Organisation liegt keine Branchenprofil-Empfehlung vor.',
    ],
    'customize' => [
        'subtitle' => 'Schalte ein, was in deinem Menü erscheinen soll — schalte aus, was du persönlich nicht brauchst. Gilt nur für dich, auf allen Geräten.',
        'cosmetic_hint' => 'Ausblenden ändert keine Berechtigungen: Suche, Lesezeichen und direkte Links funktionieren weiterhin. Über „Alle Funktionen“ holst du alles zurück.',
        'sidebar_heading' => 'Seitennavigation',
        'hide_section' => 'ganzen Bereich ausblenden',
        'hide_group' => 'Untergruppe ausblenden',
        'create_heading' => 'Schnellerstellung („Neu …“)',
        'create_hint' => 'Ausgeblendete Gruppen erscheinen nicht mehr im „Neu …“-Menü der Sidebar.',
        'checkbox_hint' => 'Eingeschaltet = im Menü sichtbar.',
        'saved' => 'Menüanpassung gespeichert.',
        'unhidden' => 'Eintrag wieder eingeblendet.',
    ],
    'functions' => [
        'focus_banner' => 'Aktiver Arbeitsbereich „:name“. Ausgeblendete Bereiche sind unten markiert — hier bleiben sie erreichbar.',
        'in_focus_hidden' => 'Im Arbeitsbereich ausgeblendet',
        'show_all' => 'Alles anzeigen',
        'subtitle' => 'Übersicht aller Bereiche mit ihrem Zustand — inklusive allem, was ausgeblendet, deaktiviert oder nicht lizenziert ist.',
        'state' => [
            'hidden_section' => 'Bereich ausgeblendet',
            'org_disabled' => 'Von der Organisation deaktiviert',
            'hidden_by_me' => 'Von mir ausgeblendet',
        ],
        'action' => [
            'unhide' => 'Einblenden',
            'enable_module' => 'Funktionsumfang öffnen',
        ],
        'upsell_hint' => 'Dieses Modul ist im aktuellen Tarif nicht enthalten.',
    ],
];
