<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ai.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * KI-Fundament (Feature 025, MVP-398/399): Capability-Registry,
 * Sensibilitäts-/Profilregeln und Budget-Defaults. Die Registry ist die
 * Quelle der Admin-Übersicht („welche KI-Funktion sieht welche Daten")
 * und der Datenfluss-Dokumentation nach Feature 016; jede neue
 * KI-Einsatzstelle registriert sich HIER — nirgendwo sonst.
 */
return [
    /*
     * Capability-Registry. Schlüssel = Capability-Key, Werte:
     *  - verb:            eines der sechs Verben (App\Enums\Ai\AiVerb)
     *  - sensitivity:     low|medium|high (App\Enums\Ai\AiSensitivity)
     *  - data_classes:    Datenklassen, die in den Prompt/Request gehen
     *                     (Datenfluss-Anzeige, Feature 016)
     *  - memory_scopes:   Gedächtnis-Ebenen, die eingespeist werden
     *                     (organization|customer|capability; MVP-401)
     *  - prompt_version:  ganzzahlig; Erhöhung invalidiert den Ergebnis-Cache
     *
     * Capability-Keys enthalten Punkte — Zugriff IMMER über die Registry
     * (literal), nie über config()-Dot-Notation.
     */
    'capabilities' => [
        // Feature 084, MVP-402: Einzelposition Rechnungsentwurf.
        'invoicing.item_text' => [
            'verb' => 'formulate',
            'sensitivity' => 'medium',
            'data_classes' => ['leistungstext', 'leistungsdatum'],
            'memory_scopes' => ['organization', 'customer', 'capability'],
            'prompt_version' => 1,
        ],
        // Feature 084, MVP-403: Blocktext aus gebündelten Zeiten.
        'invoicing.block_text' => [
            'verb' => 'summarize',
            'sensitivity' => 'medium',
            'data_classes' => ['zeiteintrags-texte', 'leistungszeitraum'],
            'memory_scopes' => ['organization', 'customer', 'capability'],
            'prompt_version' => 1,
        ],
        // Feature 025, MVP-409: Positionstext in Empfängersprache.
        'invoicing.item_translate' => [
            'verb' => 'translate',
            'sensitivity' => 'medium',
            'data_classes' => ['leistungstext', 'zielsprache'],
            'memory_scopes' => ['organization', 'customer'],
            'prompt_version' => 1,
        ],
        // Feature 084, MVP-405: Angebotspositionen.
        'quotes.item_text' => [
            'verb' => 'formulate',
            'sensitivity' => 'medium',
            'data_classes' => ['positionstext'],
            'memory_scopes' => ['organization', 'customer', 'capability'],
            'prompt_version' => 1,
        ],
    ],

    /*
     * Sensibilitätsstufen, für die Cloud-Verbindungen zulässig sind.
     * `high` ist bewusst lokal-exklusiv; Routing wechselt nie
     * stillschweigend über diese Grenze (Feature 025, Leitprinzip 6).
     */
    'cloud_allowed_sensitivities' => ['low', 'medium'],

    /*
     * Branchenprofile, die Cloud-Provider komplett sperren (Art. 9 DSGVO,
     * § 203 StGB — ambulante Pflege). Geprüft gegen
     * settings.branch_profile_code UND settings.branch_profile_versions.
     */
    'cloud_blocked_profiles' => ['pflege'],

    /*
     * Monatsbudget-Defaults je Familie (LLM: Token, Übersetzung: Zeichen).
     * null = unbegrenzt. Organisationen überschreiben org-explizit über
     * settings.ai.budget.monthly_units.llm / .translation.
     */
    'budget' => [
        'monthly_units' => [
            'llm' => null,
            'translation' => null,
        ],
    ],

    /* Ergebnis-Cache in Tagen (gleicher Input + Prompt-Version + Verbindung). */
    'cache_ttl_days' => 7,

    /*
     * Zeichen-Budget des KI-Gedächtnisses je Aufruf (MVP-401). Bei
     * Überschreitung werden Einträge nach Ebene (Kunde > Organisation >
     * Capability-Default), Nutzungshäufigkeit und Aktualität priorisiert.
     */
    'memory_budget_characters' => 4000,

    /*
     * Aufbewahrung entschiedener Textvorschläge in Tagen (Feature 084 —
     * Betriebsdaten, kein Beleg; Bereinigung über ai:maintenance).
     */
    'suggestion_retention_days' => 30,
];
