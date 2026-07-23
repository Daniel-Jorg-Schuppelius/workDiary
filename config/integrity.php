<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integrity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Quelltext-Integritätsüberwachung (Feature 095, MVP-439–442).
 *
 * Scan-Umfang der Datei-Hash-Baseline (integrity.json): Eigencode dateiweise,
 * vendor/ als Aggregat je Composer-Paket. Secrets/Laufzeitdaten (.env,
 * storage/) sind bewusst NICHT Teil der Baseline — Whitelist-Prinzip.
 */
return [

    // Täglicher Scheduler-Lauf (integrity:verify --trigger=schedule).
    'enabled' => env('INTEGRITY_CHECK_ENABLED', true),

    // Scan-Wurzel-Override — nur für Tests (null = base_path()).
    'base' => null,

    // Dateiweise gehashte Verzeichnisse (relativ zu base_path).
    'paths' => [
        'app',
        'bootstrap',
        'config',
        'database',
        'lang',
        'public',
        'resources',
        'routes',
        'scripts',
        'tests',
    ],

    // Einzeln gehashte Root-Dateien (Whitelist, .env bleibt implizit außen vor).
    'root_files' => [
        'artisan',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'vite.config.js',
    ],

    // Beim Traversieren übersprungene Unterbäume (relative Präfixe).
    'exclude' => [
        'bootstrap/cache',
        'public/storage',
        'public/hot',
    ],

    // vendor/: ein Aggregat-Hash je Composer-Paket statt Einzeldateien.
    'vendor' => [
        'enabled' => true,
        'path' => 'vendor',
    ],

    // Realtime-Wächter (Feature 097, MVP-453: integrity:watch, ext-inotify).
    'watch' => [
        // Sammelfenster: nach der letzten erkannten Änderung so lange warten,
        // bevor ein Verify ausgelöst wird — dämpft Event-Stürme (Deploy, Editor,
        // Build). Ein einzelner Verify prüft danach den GESAMTEN Scope (inkl.
        // vendor), auch wenn nur Eigencode überwacht wird.
        'debounce_seconds' => (int) env('INTEGRITY_WATCH_DEBOUNCE', 30),
    ],

    // Deckel für persistierte/ausgegebene Einzelbefunde je Kategorie.
    'max_findings' => 50,

    // Aufbewahrung der Prüfhistorie (integrity_checks) in Monaten.
    'retention_months' => (int) env('INTEGRITY_RETENTION_MONTHS', 24),
];
