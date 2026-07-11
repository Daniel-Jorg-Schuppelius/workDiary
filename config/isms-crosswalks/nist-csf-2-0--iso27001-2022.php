<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : nist-csf-2-0--iso27001-2022.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Crosswalk NIST CSF 2.0 → ISO/IEC 27001:2022 (Feature 044/046, Nachtrag NIST).
 *
 * Bildet jede CSF-2.0-Kategorie auf die inhaltlich entsprechenden
 * ISO/IEC-27001:2022-Referenzen ab (HLS-Klauseln 4–10 und/oder Annex-A-
 * Maßnahmen). Grundlage sind die informativen Referenzen des offiziellen
 * NIST-CSF-2.0 (public domain). Die Zuordnung ist bewusst auf
 * KATEGORIE-Ebene gehalten (nicht Subkategorie) — passend zu den
 * kuratierten Normprofilen in config/isms-norms/.
 *
 * Nutzung: {@see \App\Services\Isms\CrosswalkRegistry} lädt/validiert die
 * Datei; {@see \App\Services\Isms\CsfReadinessService} leitet daraus die
 * CSF-Funktionsabdeckung aus der ISO-SoA eines Geltungsbereichs ab.
 *
 * HINWEIS: Die Abbildung ist eine fachliche Orientierung, KEINE amtliche
 * Konformitätszusage. Sie ersetzt keine eigene Bewertung im Geltungsbereich.
 */

return [
    'key' => 'nist-csf-2-0--iso27001-2022',
    'source_norm' => 'NIST CSF',
    'source_edition' => '2.0',
    'target_norm' => 'ISO/IEC 27001',
    'target_edition' => '2022',
    'label' => 'NIST CSF 2.0 → ISO/IEC 27001:2022',
    'version' => '1.0',
    'as_of' => '2024-02-26',
    'mappings' => [
        // ── GOVERN (GV) ────────────────────────────────────────────────
        ['source_ref' => 'GV.OC', 'target_refs' => ['4.1', '4.2', '4.3']],
        ['source_ref' => 'GV.RM', 'target_refs' => ['6.1', '6.2', 'A.5.1']],
        ['source_ref' => 'GV.RR', 'target_refs' => ['5.1', '5.3', 'A.5.2', 'A.5.3', 'A.5.4']],
        ['source_ref' => 'GV.PO', 'target_refs' => ['5.2', 'A.5.1', 'A.5.37']],
        ['source_ref' => 'GV.OV', 'target_refs' => ['9.1', '9.3', 'A.5.35', 'A.5.36']],
        ['source_ref' => 'GV.SC', 'target_refs' => ['A.5.19', 'A.5.20', 'A.5.21', 'A.5.22', 'A.5.23']],

        // ── IDENTIFY (ID) ──────────────────────────────────────────────
        ['source_ref' => 'ID.AM', 'target_refs' => ['A.5.9', 'A.5.10', 'A.5.12', 'A.8.1']],
        ['source_ref' => 'ID.RA', 'target_refs' => ['6.1', 'A.5.7', 'A.8.8']],
        ['source_ref' => 'ID.IM', 'target_refs' => ['9.1', '10.1', '10.2']],

        // ── PROTECT (PR) ───────────────────────────────────────────────
        ['source_ref' => 'PR.AA', 'target_refs' => ['A.5.15', 'A.5.16', 'A.5.17', 'A.5.18', 'A.8.2', 'A.8.3', 'A.8.5']],
        ['source_ref' => 'PR.AT', 'target_refs' => ['7.2', '7.3', 'A.6.3']],
        ['source_ref' => 'PR.DS', 'target_refs' => ['A.5.14', 'A.8.10', 'A.8.11', 'A.8.12', 'A.8.13', 'A.8.24']],
        ['source_ref' => 'PR.PS', 'target_refs' => ['A.8.7', 'A.8.9', 'A.8.19', 'A.8.25', 'A.8.28', 'A.8.31', 'A.8.32']],
        ['source_ref' => 'PR.IR', 'target_refs' => ['A.7.5', 'A.7.11', 'A.8.14', 'A.8.20', 'A.8.22']],

        // ── DETECT (DE) ────────────────────────────────────────────────
        ['source_ref' => 'DE.CM', 'target_refs' => ['A.8.6', 'A.8.15', 'A.8.16']],
        ['source_ref' => 'DE.AE', 'target_refs' => ['A.5.25', 'A.8.15', 'A.8.16']],

        // ── RESPOND (RS) ───────────────────────────────────────────────
        ['source_ref' => 'RS.MA', 'target_refs' => ['A.5.24', 'A.5.26']],
        ['source_ref' => 'RS.AN', 'target_refs' => ['A.5.25', 'A.5.27', 'A.5.28']],
        ['source_ref' => 'RS.CO', 'target_refs' => ['A.5.5', 'A.5.6', 'A.6.8']],
        ['source_ref' => 'RS.MI', 'target_refs' => ['A.5.26', 'A.8.7']],

        // ── RECOVER (RC) ───────────────────────────────────────────────
        ['source_ref' => 'RC.RP', 'target_refs' => ['A.5.29', 'A.5.30', 'A.8.13', 'A.8.14']],
        ['source_ref' => 'RC.CO', 'target_refs' => ['A.5.5', 'A.5.6']],
    ],
];
