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
        // Feature 084, MVP-405 (Phase-36-Rest): E-Mail-Begleittext beim
        // Rechnungsversand — Entwurf im Versand-Dialog, nie Auto-Versand.
        'invoicing.mail_text' => [
            'verb' => 'formulate',
            'sensitivity' => 'medium',
            'data_classes' => ['rechnungsmetadaten'],
            'memory_scopes' => ['organization', 'customer', 'capability'],
            'prompt_version' => 1,
        ],
        // Feature 084, MVP-405 (Phase-36-Rest): Zahlungserinnerung/Mahntext
        // (Stufe 1–3) — Entwurf im Mahn-Dialog, nie Auto-Versand.
        'invoicing.dunning_text' => [
            'verb' => 'formulate',
            'sensitivity' => 'medium',
            'data_classes' => ['rechnungsmetadaten', 'mahnstufe'],
            'memory_scopes' => ['organization', 'customer', 'capability'],
            'prompt_version' => 1,
        ],
        // Feature 088: Feld-Extraktion beim Rechnungsdatei-Import — nur
        // Fallback, wenn die Regel-Heuristik Kernfelder nicht findet; NIE
        // für strukturierte E-Rechnungen (XML gewinnt), nie Auto-Übernahme
        // ohne Prüfschritt. Belege können PII enthalten → hoch sensibel.
        'invoicing.document_extraction' => [
            'verb' => 'extract',
            'sensitivity' => 'high',
            'data_classes' => ['belegtext'],
            'memory_scopes' => [],
            'prompt_version' => 1,
        ],
        // Feature 084 (Phase-36-Rest): Portal-Antwort in Kundensprache
        // übersetzen — Vorschau-Entwurf im Antwortformular, nie Auto-Versand.
        'portal.answer_translate' => [
            'verb' => 'translate',
            'sensitivity' => 'medium',
            'data_classes' => ['antworttext', 'zielsprache'],
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
        // Feature 143, MVP-711: Mangel-/Zustandsfreitext eines Protokollpunkts
        // veredeln — nur umformulieren, keine neuen Fakten; nur in
        // bearbeitbaren Protokollen (signiert/archiviert = gesperrt).
        'protocols.item_text' => [
            'verb' => 'formulate',
            'sensitivity' => 'medium',
            'data_classes' => ['mangel-/zustandstext', 'protokolltitel', 'punktbezeichnung'],
            'memory_scopes' => ['organization', 'customer', 'capability'],
            'prompt_version' => 1,
        ],
        // Feature 143, MVP-711: Schweregrad/Kategorie/Ergebnis eines
        // Protokollpunkts aus dem Freitext vorschlagen — Katalog = Fehlertypen
        // der Organisation + feste Schweregrade/Ergebnisse; nie Auto-Apply.
        'protocols.item_classify' => [
            'verb' => 'classify',
            'sensitivity' => 'medium',
            'data_classes' => ['mangel-/zustandstext', 'katalogwerte'],
            'memory_scopes' => ['organization'],
            'prompt_version' => 1,
        ],
        // Feature 143, MVP-711: Tags/Katalogwerte aus Freitext vorschlagen —
        // Ergebnis wird auf bestehende Tags/Katalogwerte gemappt, unbekannte
        // Vorschläge verworfen (KI legt nie Tags an).
        'classification.tag_suggest' => [
            'verb' => 'classify',
            'sensitivity' => 'low',
            'data_classes' => ['freitext', 'tag-namen', 'katalogwerte'],
            'memory_scopes' => ['organization'],
            'prompt_version' => 1,
        ],
        // ── Feature 148, MVP-732 — KI-Welle 2 (Zusammenfassen/Erklären/Übersetzen) ──
        // Angebots-/AB-/Lieferschein-Positionen und Begleittexte in die
        // BELEGSPRACHE (Feature 034, MVP-721) statt in eine frei gewählte
        // Sprache — terminologietreu über das Gedächtnis-Glossar.
        'documents.item_translate' => [
            'verb' => 'translate',
            'sensitivity' => 'medium',
            'data_classes' => ['positionstext', 'begleittext', 'belegsprache'],
            'memory_scopes' => ['organization', 'customer'],
            'prompt_version' => 1,
        ],
        // Fremdsprachige Portal-Rückfrage für Bearbeiter verständlich machen:
        // Übersetzung + Kurzfassung in EINEM Zusammenfassungs-Aufruf. Der
        // Kundentext wird vorher maskiert; mittel = Cloud nur bei erlaubtem
        // Branchenprofil (Pflege bleibt gesperrt).
        'portal.query_understand' => [
            'verb' => 'summarize',
            'sensitivity' => 'medium',
            'data_classes' => ['rückfragetext', 'vorgangsbezeichnung'],
            'memory_scopes' => ['organization'],
            'prompt_version' => 1,
        ],
        // Telefonat-/Gesprächsverlauf → strukturierte Notiz (Betreff, Ergebnis,
        // Folgeaktion). Gesprächsinhalte sind heikel → hoch = lokal-exklusiv;
        // als vertraulich markierte Notizen sind zusätzlich ganz gesperrt.
        'communication.note_structure' => [
            'verb' => 'extract',
            'sensitivity' => 'high',
            'data_classes' => ['gesprächsnotiz'],
            'memory_scopes' => [],
            'prompt_version' => 1,
        ],
        // Auftragsverlauf (Fallakte/Timeline) → Kurznarrativ. Der Verlauf
        // enthält interne Ereignisse quer über alle Quellen → hoch.
        'case.timeline_narrative' => [
            'verb' => 'summarize',
            'sensitivity' => 'high',
            'data_classes' => ['verlaufsereignisse'],
            'memory_scopes' => ['organization'],
            'prompt_version' => 1,
        ],
        // Plan-Ist-Abweichung erklären: ausschließlich benannte Kennzahlen,
        // keine Namen, keine Datensätze → niedrig.
        'plan_actual.explain' => [
            'verb' => 'explain',
            'sensitivity' => 'low',
            'data_classes' => ['kennzahlen'],
            'memory_scopes' => ['organization'],
            'prompt_version' => 1,
        ],
        // Supportbericht/Fehlercodes erklären — Fakten kommen über eine feste
        // Whitelist technischer Schlüssel und werden zusätzlich redigiert
        // (keine Pfade/Adressen/IPs). Idealer Cloud-Pilot.
        'support.diagnose_explain' => [
            'verb' => 'explain',
            'sensitivity' => 'low',
            'data_classes' => ['technische-statuswerte'],
            'memory_scopes' => [],
            'prompt_version' => 1,
        ],
        // ── Feature 148, MVP-732 — KI-Welle 3 (Extraktion und Suche) ──
        // DMS: Dokumenttyp erkennen + Metadaten/Fristen extrahieren. EIN
        // Aufruf statt Classify+Extract — der Dokumenttyp ist ein Feld des
        // Zielschemas mit abschließender Werteliste; das Rückmapping auf
        // DocumentType verwirft Unbekanntes (Katalog-Garantie). Dokumente
        // können PII enthalten → hoch (wie invoicing.document_extraction).
        'dms.classify_extract' => [
            'verb' => 'extract',
            'sensitivity' => 'high',
            'data_classes' => ['dokumenttext', 'ocr-text'],
            'memory_scopes' => [],
            'prompt_version' => 1,
        ],
        // Import-Drehscheibe: Kopfzeile → kanonische Spec-Spalte vorschlagen.
        // Es gehen ausschließlich KOPFZELLEN in den Prompt, nie Datenzeilen.
        'import.column_mapping' => [
            'verb' => 'classify',
            'sensitivity' => 'low',
            'data_classes' => ['kopfzeilen', 'spaltenkatalog'],
            'memory_scopes' => [],
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
