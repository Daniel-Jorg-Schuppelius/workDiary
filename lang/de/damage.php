<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : damage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Schadensfälle (MVP-919/920).
return [
    'title' => 'Schadensfälle',
    'subtitle' => 'Versicherungs- und Schadensfälle an Verleih, Leasing, Reklamation und Fahrzeug — mit Regulierung, Selbstbehalt und Verlauf.',
    'case' => 'Schadensfall',
    'empty' => 'Keine Schadensfälle — angelegt werden sie an der Akte (Verleih, Leasing, Reklamation, Fahrzeug).',
    'nav' => [
        'section' => 'Schaden & Rückruf',
        'cases' => 'Schadensfälle',
    ],
    'kpi' => [
        'open' => 'Offene Fälle',
        'open_estimate' => 'Geschätzt (offen)',
        'settled' => 'Reguliert',
    ],
    'filter' => [
        'all_status' => 'Alle Status',
        'all_subjects' => 'Alle Akten',
        'all_kinds' => 'Alle Arten',
    ],
    'field' => [
        'number' => 'Nummer',
        'title' => 'Bezeichnung',
        'subject' => 'Bezug',
        'kind' => 'Schadensart',
        'status' => 'Status',
        'estimated_amount' => 'Geschätzter Schaden',
        'settled_amount' => 'Regulierter Betrag',
        'deductible_amount' => 'Selbstbehalt',
        'net_recovery' => 'Erstattung nach Selbstbehalt',
        'currency' => 'Währung',
        'occurred_at' => 'Schadenszeitpunkt',
        'reported_at' => 'Gemeldet am',
        'insurer_name' => 'Versicherer',
        'policy_number' => 'Policennummer',
        'claim_number' => 'Schadennummer',
        'responsible_user_id' => 'Verantwortlich',
        'description' => 'Hergang',
    ],
    'section' => [
        'case' => 'Schadensfall',
        'insurance' => 'Versicherung',
        'amounts' => 'Beträge',
        'status' => 'Status ändern',
        'journal' => 'Verlauf',
    ],
    'action' => [
        'show' => 'Anzeigen',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'open' => 'Schadensfall anlegen',
        'report' => 'Schaden melden',
    ],
    'dialog' => [
        'create' => 'Schaden melden',
        'edit' => 'Schadensfall bearbeiten',
    ],
    'card' => [
        'title' => 'Schadensfälle',
        'none' => 'Keine Schadensfälle.',
    ],
    'status' => [
        'reported' => 'Gemeldet',
        'submitted' => 'Beim Versicherer eingereicht',
        'in_review' => 'In Prüfung',
        'settled' => 'Reguliert',
        'rejected' => 'Abgelehnt',
        'closed' => 'Abgeschlossen',
    ],
    'transition' => [
        'submitted' => 'Beim Versicherer einreichen',
        'in_review' => 'In Prüfung',
        'settled' => 'Als reguliert erfassen',
        'rejected' => 'Ablehnung erfassen',
        'closed' => 'Abschließen',
    ],
    'kind' => [
        'property' => 'Sachschaden',
        'liability' => 'Haftpflicht',
        'theft' => 'Diebstahl/Verlust',
        'vehicle' => 'Fahrzeugschaden',
        'transport' => 'Transportschaden',
        'other' => 'Sonstiges',
    ],
    'error' => [
        'settled_amount_required' => 'Für „reguliert“ fehlt der regulierte Betrag.',
    ],
    'flash' => [
        'opened' => 'Schadensfall :number angelegt.',
        'saved' => 'Schadensfall gespeichert.',
        'status' => 'Status: :status.',
    ],
    'subject_type' => [
        'rental_cases' => 'Verleihvorgang',
        'asset_finance_contracts' => 'Leasing-/Finanzierungsvertrag',
        'claim_cases' => 'Reklamation',
        'vehicles' => 'Fahrzeug',
    ],
];
