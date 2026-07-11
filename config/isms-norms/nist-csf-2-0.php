<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : nist-csf-2-0.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Normprofil NIST Cybersecurity Framework (CSF) 2.0 — Cybersicherheit
 * (Feature 044/046, Nachtrag NIST).
 *
 * Strukturebene: die sechs CSF-2.0-Funktionen und ihre Kategorien
 * (Function-/Category-Identifier). Subkategorien (Subcategory-Level) sind
 * bewusst NICHT hier hinterlegt — sie kommen bei Bedarf über den
 * OSCAL-Katalog-Import (044a, {@see \App\Services\Isms\RequirementService})
 * aus dem frei lizenzierten NIST-OSCAL-Content
 * (github.com/usnistgov/oscal-content).
 *
 * URHEBERRECHT: Das NIST CSF 2.0 ist ein Werk der US-Bundesregierung und
 * gemeinfrei (public domain, NIST SP 800 / CSWP). Die hier geführten
 * Kurztitel sind eigene deutsche Bezeichnungen der offiziellen
 * Funktions-/Kategorienamen — anders als bei den ISO-Profilen wäre auch
 * der Volltext frei einbettbar (siehe OSCAL-Import).
 *
 * Schema siehe {@see \App\Services\Isms\NormProfileRegistry}.
 */

return [
    'key' => 'nist-csf-2-0',
    'norm' => 'NIST CSF',
    'edition' => '2.0',
    'label' => 'NIST Cybersecurity Framework 2.0',
    // Profilrevision + Stichtag der Normfassung (CSWP 29, 2024-02-26).
    'version' => '1.0',
    'as_of' => '2024-02-26',
    'requirements' => [
        // ── GOVERN (GV) ─────────────────────────────────────────────────
        ['ref_no' => 'GV', 'title' => 'Führung/Governance'],
        ['ref_no' => 'GV.OC', 'title' => 'Organisatorischer Kontext'],
        ['ref_no' => 'GV.RM', 'title' => 'Risikomanagementstrategie'],
        ['ref_no' => 'GV.RR', 'title' => 'Rollen, Verantwortlichkeiten und Befugnisse'],
        ['ref_no' => 'GV.PO', 'title' => 'Richtlinien'],
        ['ref_no' => 'GV.OV', 'title' => 'Aufsicht durch die Leitung'],
        ['ref_no' => 'GV.SC', 'title' => 'Risikomanagement der Cybersicherheits-Lieferkette'],

        // ── IDENTIFY (ID) ───────────────────────────────────────────────
        ['ref_no' => 'ID', 'title' => 'Identifizieren'],
        ['ref_no' => 'ID.AM', 'title' => 'Asset-Management'],
        ['ref_no' => 'ID.RA', 'title' => 'Risikobewertung'],
        ['ref_no' => 'ID.IM', 'title' => 'Verbesserung'],

        // ── PROTECT (PR) ────────────────────────────────────────────────
        ['ref_no' => 'PR', 'title' => 'Schützen'],
        ['ref_no' => 'PR.AA', 'title' => 'Identitätsmanagement, Authentisierung und Zugangssteuerung'],
        ['ref_no' => 'PR.AT', 'title' => 'Bewusstsein und Schulung'],
        ['ref_no' => 'PR.DS', 'title' => 'Datensicherheit'],
        ['ref_no' => 'PR.PS', 'title' => 'Plattformsicherheit'],
        ['ref_no' => 'PR.IR', 'title' => 'Resilienz der Technologie-Infrastruktur'],

        // ── DETECT (DE) ─────────────────────────────────────────────────
        ['ref_no' => 'DE', 'title' => 'Erkennen'],
        ['ref_no' => 'DE.CM', 'title' => 'Kontinuierliche Überwachung'],
        ['ref_no' => 'DE.AE', 'title' => 'Analyse nachteiliger Ereignisse'],

        // ── RESPOND (RS) ────────────────────────────────────────────────
        ['ref_no' => 'RS', 'title' => 'Reagieren'],
        ['ref_no' => 'RS.MA', 'title' => 'Vorfallmanagement'],
        ['ref_no' => 'RS.AN', 'title' => 'Vorfallanalyse'],
        ['ref_no' => 'RS.CO', 'title' => 'Berichterstattung und Kommunikation bei Vorfällen'],
        ['ref_no' => 'RS.MI', 'title' => 'Eindämmung von Vorfällen'],

        // ── RECOVER (RC) ────────────────────────────────────────────────
        ['ref_no' => 'RC', 'title' => 'Wiederherstellen'],
        ['ref_no' => 'RC.RP', 'title' => 'Ausführung des Wiederherstellungsplans'],
        ['ref_no' => 'RC.CO', 'title' => 'Kommunikation der Wiederherstellung'],
    ],
];
