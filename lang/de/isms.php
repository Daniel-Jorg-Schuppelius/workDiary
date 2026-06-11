<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : isms.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'section' => 'ISMS',
        'risks' => 'Risikoregister',
        'controls' => 'Maßnahmenkatalog',
        'soa' => 'SoA',
    ],

    'subtitle' => [
        'risks' => 'Informationssicherheitsrisiken erfassen, bewerten (5×5) und behandeln.',
        'controls' => 'Maßnahmen (Controls) pflegen und die SoA-Aussage je Control dokumentieren.',
    ],

    'field' => [
        'risk_no' => 'Nr.',
        'title' => 'Titel',
        'description' => 'Beschreibung',
        'category' => 'Kategorie',
        'asset_ref' => 'Bezug (System/Prozess/Standort)',
        'threat' => 'Bedrohung',
        'likelihood' => 'Eintrittswahrscheinlichkeit',
        'impact' => 'Auswirkung',
        'score' => 'Score',
        'treatment' => 'Behandlung',
        'status' => 'Status',
        'owner' => 'Verantwortlich',
        'review_due_on' => 'Review fällig',
        'controls' => 'Verknüpfte Maßnahmen',
        'code' => 'Code',
        'source' => 'Quelle',
        'applicable' => 'Anwendbar',
        'justification' => 'Begründung',
        'implementation_status' => 'Umsetzungsstatus',
        'evidence_note' => 'Evidenz-Notiz',
        'risks' => 'Verknüpfte Risiken',
    ],

    'group' => [
        'risk' => 'Risiko',
        'assessment' => 'Bewertung & Behandlung',
        'control' => 'Maßnahme',
        'soa' => 'Statement of Applicability',
    ],

    'action' => [
        'create_risk' => 'Risiko erfassen',
        'edit_risk' => 'Risiko bearbeiten',
        'create_control' => 'Maßnahme anlegen',
        'edit_control' => 'Maßnahme bearbeiten',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'delete' => 'Löschen',
        'transition' => 'Status ändern',
        'import_catalog' => 'Annex-A-Katalog laden',
        'back' => 'Zurück',
        'print' => 'Drucken / PDF speichern',
    ],

    'filter' => [
        'all' => 'Alle',
        'sort' => 'Sortierung',
        'sort_score' => 'Höchster Score zuerst',
        'sort_review' => 'Review-Termin',
        'sort_newest' => 'Neueste zuerst',
        'applicable_yes' => 'Anwendbar',
        'applicable_no' => 'Nicht anwendbar',
    ],

    'scale' => [
        'likelihood' => [
            1 => 'sehr selten',
            2 => 'selten',
            3 => 'möglich',
            4 => 'wahrscheinlich',
            5 => 'sehr wahrscheinlich',
        ],
        'impact' => [
            1 => 'unwesentlich',
            2 => 'gering',
            3 => 'spürbar',
            4 => 'schwerwiegend',
            5 => 'existenzbedrohend',
        ],
    ],

    'matrix' => [
        'title' => 'Risikomatrix (offene Risiken)',
        'cell' => 'Wahrscheinlichkeit :likelihood × Auswirkung :impact — :count Risiko/Risiken',
        'axes' => 'Zeilen: Eintrittswahrscheinlichkeit (1–5) · Spalten: Auswirkung (1–5)',
        'legend' => 'Legende',
        'low' => 'Niedrig (Score ≤ 6)',
        'medium' => 'Mittel (Score 7–12)',
        'high' => 'Hoch (Score > 12)',
        'review_due' => '{1} 1 Review fällig|[2,*] :count Reviews fällig',
    ],

    'hint' => [
        'asset_ref' => 'z. B. ERP-System, Serverraum, Rechenzentrum …',
        'threat' => 'Welche Bedrohung/Schwachstelle liegt zugrunde?',
        'controls' => 'Mehrfachauswahl (Strg/Cmd gedrückt halten)',
        'no_controls_yet' => 'Noch keine Maßnahmen vorhanden — zuerst den Annex-A-Katalog laden oder eigene Maßnahmen anlegen.',
        'code' => 'z. B. M-01 (eigene Maßnahme)',
        'justification' => 'Pflicht, wenn nicht anwendbar',
        'evidence_note' => 'Verweis auf Nachweis/Dokument',
    ],

    'flash' => [
        'risk_created' => 'Risiko wurde erfasst.',
        'risk_updated' => 'Risiko wurde aktualisiert.',
        'risk_transitioned' => 'Risikostatus wurde geändert.',
        'risk_deleted' => 'Risiko wurde gelöscht.',
        'control_created' => 'Maßnahme wurde angelegt.',
        'control_updated' => 'Maßnahme wurde aktualisiert.',
        'control_deleted' => 'Maßnahme wurde gelöscht.',
        'catalog_imported' => 'Annex-A-Katalog geladen (:count neue Controls).',
    ],

    'error' => [
        'invalid_transition' => 'Statuswechsel von ":from" nach ":to" ist nicht zulässig.',
        'justification_required' => 'Für nicht anwendbare Controls ist eine SoA-Begründung erforderlich.',
    ],

    'soa' => [
        'document_title' => 'Statement of Applicability',
        'heading' => 'Statement of Applicability (SoA)',
        'generated_at' => 'Stand',
        'control_count' => ':count Controls',
        'yes' => 'Ja',
        'no' => 'Nein',
        'disclaimer' => 'Referenz: ISO/IEC 27001:2022 Anhang A (nur Codes und eigene Kurztitel — keine Normtexte). Die Konformitätsbewertung erfolgt durch eine unabhängige Zertifizierungsstelle.',
    ],

    'empty_risks' => 'Noch keine Risiken erfasst.',
    'empty_risks_title' => 'Keine Risiken gefunden',
    'empty_controls' => 'Noch keine Maßnahmen vorhanden.',
    'empty_controls_title' => 'Keine Maßnahmen gefunden',
    'empty_controls_hint_catalog' => 'Noch keine Maßnahmen vorhanden — über „Annex-A-Katalog laden" den ISO/IEC-27001-Referenzkatalog (93 Controls) übernehmen.',
    'empty_controls_linked' => 'Keine Maßnahmen verknüpft.',
    'empty_filtered' => 'Für die aktuellen Filter wurden keine Einträge gefunden.',
    'confirm_delete_risk' => 'Risiko wirklich löschen?',
    'confirm_delete_control' => 'Maßnahme wirklich löschen? Verknüpfungen zu Risiken werden gelöst.',
    'confirm_import_catalog' => 'ISO/IEC 27001:2022 Annex-A-Referenzkatalog (93 Controls, nur Code + Kurztitel) in diese Organisation laden? Bereits vorhandene Controls bleiben unverändert.',
];
