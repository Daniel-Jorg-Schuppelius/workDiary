<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : print.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Druckerzeugnisse / Kopiershop (MVP-459, Branchenprofil druck-kopiershop).
return [
    'document_title' => 'Druckdaten :number',

    'nav' => [
        'section' => 'Druck & Kopiershop',
    ],

    'orders' => [
        'title' => 'Druckaufträge',
        'subtitle' => 'Datenannahme, Preflight, Druckfreigabe, Produktion, Qualitätskontrolle und Ausgabe — reproduzierbar am Fertigungsauftrag.',
        'detail_title' => 'Druckauftrag',
        'empty' => 'Keine Druckaufträge im Zeitraum — neue Aufträge entstehen über den Dialog.',
        'kpi' => [
            'open' => 'Offene Druckaufträge',
        ],
        'action' => [
            'create' => 'Neuer Druckauftrag',
            'create_submit' => 'Auftrag anlegen',
            'manufacturing' => 'Fertigungsauftrag',
            'bind_file' => 'Datei binden',
            'run_preflight' => 'Preflight ausführen',
            'override' => 'Begründet übersteuern',
            'manual_preflight' => 'Manuellen Befund speichern',
            'approve' => 'Druckfreigabe erteilen',
            'start_production' => 'Produktion starten',
            'resume_production' => 'Produktion fortsetzen',
            'quality_check' => 'QK dokumentieren',
            'issue' => 'Ausgeben',
            'cancel' => 'Stornieren',
        ],
    ],

    'section' => [
        'order' => 'Auftrag',
        'file' => 'Produktionsdatei & Preflight',
        'approval' => 'Druckfreigabe & Snapshot',
        'production' => 'Produktion, QK & Ausgabe',
        'claims' => 'Reklamationen',
    ],

    'field' => [
        'article' => 'Artikel/Druckprodukt',
        'quantity' => 'Menge',
        'unit' => 'Einheit',
        'customer_optional' => 'Kunde (optional)',
        'walk_in' => 'Laufkundschaft (datensparsam)',
        'due_at' => 'Solltermin',
        'output_kind' => 'Ausgabeart',
        'files_retain_until' => 'Löschfrist Produktionsdatei',
        'preflight' => 'Preflight',
        'file' => 'Datei',
        'file_hash' => 'Prüfsumme (SHA-256)',
        'file_bound_at' => 'Gebunden am',
        'preflight_provider' => 'Prüfwerkzeug',
        'preflight_at' => 'Geprüft am',
        'override_reason' => 'Override-Begründung',
        'manual_errors' => 'Fehler (eine je Zeile)',
        'manual_warnings' => 'Warnungen (eine je Zeile)',
        'approved_by' => 'Freigegeben von',
        'approved_at' => 'Freigegeben am',
        'approved_file_hash' => 'Freigegebene Prüfsumme',
        'machine' => 'Maschine',
        'without_machine' => 'ohne Maschinenbindung',
        'production_started_at' => 'Produktionsstart',
        'qc_status' => 'QK-Ergebnis',
        'qc_by' => 'QK durch',
        'qc_note' => 'QK-Notiz',
        'issued_at' => 'Ausgegeben am',
        'handover_name' => 'Übergabe an',
        'handover_note' => 'Übergabenotiz',
        'shipment' => 'Sendung',
        'reason' => 'Begründung',
        'good_total' => 'Gutmenge',
        'scrap_total' => 'Makulatur',
        'cancel_reason' => 'Stornogrund',
    ],

    'snapshot' => [
        'final_format' => 'Endformat',
        'pages' => 'Seiten',
        'orientation' => 'Ausrichtung',
        'bleed_mm' => 'Beschnitt (mm)',
        'safety_mm' => 'Sicherheitsabstand (mm)',
        'color_mode' => 'Farbigkeit',
        'color_profile' => 'Farbprofil',
        'spot_colors' => 'Sonderfarben',
        'material' => 'Material/Bedruckstoff',
        'grammage' => 'Grammatur',
        'quantity' => 'Menge',
        'due_date' => 'Solltermin',
        'finishing' => 'Weiterverarbeitung',
    ],

    'badge' => [
        'approval_stale' => 'Datei geändert — Freigabe ungültig',
        'file_purged' => 'Datei nach Löschfrist entfernt',
    ],

    'qc' => [
        'passed' => 'Freigegeben',
        'rework' => 'Nacharbeit',
        'blocked' => 'Gesperrt',
    ],

    'hint' => [
        'retention' => 'Nach Ablauf wird nur die Kundendatei entfernt — Auftrag, Snapshot und Prüfsumme bleiben als kaufmännischer Nachweis.',
        'no_snapshot' => 'Noch keine Druckfreigabe — Parameter werden bei der Freigabe als unveränderlicher Snapshot eingefroren.',
        'counter_minimal' => 'Tresenverkauf: keine personenbezogenen Angaben nötig.',
        'claim_reference' => 'Der Fall wird mit dem Druckauftrag verknüpft — freigegebene Datei, Produktions-Snapshot und QK-Ergebnis bleiben darüber referenzierbar.',
    ],

    'flash' => [
        'created' => 'Druckauftrag angelegt.',
        'file_bound' => 'Produktionsdatei gebunden (Prüfsumme gesichert).',
        'preflight_recorded' => 'Preflight-Befund gespeichert.',
        'preflight_overridden' => 'Preflight begründet übersteuert.',
        'approved' => 'Druckfreigabe erteilt — Snapshot eingefroren.',
        'production_started' => 'Produktion läuft.',
        'quality_checked' => 'Qualitätskontrolle dokumentiert.',
        'issued' => 'Auftrag ausgegeben.',
        'cancelled' => 'Auftrag storniert.',
        'claim_opened' => 'Reklamation :number angelegt.',
    ],

    'preflight' => [
        'file_missing' => 'Produktionsdatei ist im Speicher nicht auffindbar.',
        'file_empty' => 'Die Datei ist leer (0 Byte).',
        'mime_unexpected' => 'Unerwarteter Dateityp „:mime" — für den Druck prüfen.',
        'pdf_header_invalid' => 'Die Datei ist als PDF deklariert, hat aber keinen gültigen PDF-Header.',
    ],

    'error' => [
        'order_already_specialized' => 'Zu diesem Fertigungsauftrag existiert bereits ein Druckauftrag (1:1).',
        'order_closed' => 'Der Druckauftrag ist abgeschlossen — keine Dateiänderung mehr möglich.',
        'document_mismatch' => 'Dokument/Version passen nicht zusammen oder gehören nicht zu dieser Organisation.',
        'file_required' => 'Zuerst eine Produktionsdatei binden.',
        'provider_unsupported' => 'Das Prüfwerkzeug unterstützt diesen Dateityp nicht.',
        'override_only_failed' => 'Nur blockierende Preflight-Fehler können übersteuert werden.',
        'override_reason_required' => 'Der Override braucht eine Begründung.',
        'preflight_blocks_approval' => 'Preflight offen oder fehlerhaft — Freigabe erst nach Prüfung oder begründetem Override.',
        'parameter_required' => 'Pflichtangabe fehlt: :parameter.',
        'approval_stale' => 'Die Datei wurde nach der Freigabe geändert — der Auftrag ist wieder prüf-/freigabepflichtig.',
        'machine_foreign' => 'Die Maschine gehört nicht zu dieser Organisation.',
        'machine_inspection_overdue' => 'Maschine mit überfälliger Pflichtprüfung/Kalibrierung — Produktionsstart nicht zulässig.',
        'qc_result_invalid' => 'Unzulässiges QK-Ergebnis.',
        'invalid_transition' => 'Unzulässiger Statuswechsel.',
        'invalid_transition_detail' => 'Unzulässiger Statuswechsel: :from → :to.',
        'shipment_required' => 'Versand-Ausgabe braucht eine vorhandene Sendung.',
        'handover_required' => 'Abholung braucht einen Übergabenachweis (Name).',
        'cancel_reason_required' => 'Storno braucht eine Begründung.',
        'file_missing_storage' => 'Die Dateiversion ist im Storage nicht vorhanden.',
    ],

    // Reklamation am Druckauftrag (Issue #75).
    'claim' => [
        'title' => 'Reklamation Druckauftrag :number',
        'none' => 'Keine Reklamationen zu diesem Auftrag.',
        'description' => 'Beschreibung',
        'affected_quantity' => 'Betroffene Menge',
        'affected_quantity_note' => 'Betroffene Menge: :quantity',
        'open' => 'Reklamation anlegen',
    ],
];
