<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integrity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Integritäts-Sekundärsignale und Lockdown (Feature 097, MVP-447/448).
return [
    'anchor' => [
        'unavailable' => 'Externer Integritätsanker nicht lesbar (Backupziel erreichbar?) — Sekundärsignal übersprungen.',
        'root_mismatch' => 'Externer Anker weicht ab: Anker-Root :remote, lokal :local.',
        'history_mismatch' => 'Prüfhistorie weicht vom externen Anker ab — lokaler Verlauf könnte ersetzt worden sein.',
    ],
    'env' => [
        'missing' => '.env fehlt oder ist nicht lesbar (Baseline kennt einen Fingerabdruck).',
        'values_changed' => '.env geändert (Schlüsselsatz gleich, Werte abweichend).',
        'keys_changed' => '.env geändert (Schlüsselsatz abweichend: :before → :after Schlüssel).',
    ],
    'git' => [
        'head_mismatch' => 'Git-HEAD :head passt nicht zum Baseline-Build :expected (WARN).',
        'dirty' => 'Git-Arbeitsbaum im Prüfumfang nicht sauber: :count Pfad(e) — :paths (WARN).',
    ],
    'lockdown' => [
        'crisis_title' => 'Integritäts-Lockdown: Quelltext manipuliert',
        'crisis_description' => 'Signierte Release-Baseline zeigt über mehrere Läufe Abweichungen (:modified geändert, :added neu, :deleted gelöscht). Installation ist im Wartungsmodus.',
    ],
];
