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
        'subtitle' => 'Blende Menübereiche aus, die du persönlich nicht brauchst. Die Einstellung gilt nur für dich und auf allen Geräten.',
        'cosmetic_hint' => 'Ausblenden ändert keine Berechtigungen: Suche, Lesezeichen und direkte Links funktionieren weiterhin. Über „Alle Funktionen“ holst du alles zurück.',
        'sidebar_heading' => 'Seitennavigation',
        'hide_section' => 'ganzen Bereich ausblenden',
        'hide_group' => 'Untergruppe ausblenden',
        'create_heading' => 'Schnellerstellung („Neu …“)',
        'create_hint' => 'Ausgeblendete Gruppen erscheinen nicht mehr im „Neu …“-Menü der Sidebar.',
        'checkbox_hint' => 'Angehakt = ausgeblendet.',
        'saved' => 'Menüanpassung gespeichert.',
        'unhidden' => 'Eintrag wieder eingeblendet.',
    ],
    'functions' => [
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
