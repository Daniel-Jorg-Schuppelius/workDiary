<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : training.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'section' => 'Schulungen',
    'nav' => [
        'courses' => 'Schulungskatalog',
        'requirements' => 'Pflichtmatrix',
        'assignments' => 'Schulungs-Soll',
    ],
    'title' => [
        'courses' => 'Schulungskatalog',
        'requirements' => 'Pflichtmatrix',
        'assignments' => 'Schulungs-Soll',
    ],
    'subtitle' => [
        'courses' => 'Kurse mit Anbieter, Dauer, Gültigkeit und Rechtsgrundlage — Nachweise liegen im Arbeitsschutz-Register.',
        'requirements' => 'Welche Rolle bzw. welcher Tätigkeitsbereich welchen Kurs schuldet; daraus entsteht das Soll je Person.',
        'assignments' => 'Wer schuldet welche Schulung bis wann — mit Nachweis aus der Unterweisung.',
    ],

    'field' => [
        'code' => 'Kurscode',
        'title' => 'Titel',
        'provider_kind' => 'Anbieter',
        'provider_name' => 'Anbietername',
        'duration_minutes' => 'Dauer (Minuten)',
        'validity_months' => 'Gültigkeit (Monate)',
        'is_mandatory' => 'Pflichtschulung',
        'legal_basis' => 'Rechtsgrundlage',
        'cost' => 'Kosten',
        'cost_amount' => 'Kosten (informativ)',
        'cost_currency' => 'Währung',
        'lead_days' => 'Vorlauf (Tage)',
        'notes' => 'Hinweise',
        'is_active' => 'Aktiv',
        'course' => 'Kurs',
        'version' => 'Kursversion',
        'versions' => 'Kursversionen',
        'version_label' => 'Versionsbezeichnung',
        'valid_from' => 'Gültig ab',
        'content_summary' => 'Inhaltsüberblick',
        'subject' => 'Zielgruppe',
        'subject_kind' => 'Art der Zielgruppe',
        'subject_role' => 'Rolle',
        'subject_team' => 'Tätigkeitsbereich (Team)',
        'first_due_days' => 'Erst-Termin (Tage)',
        'user' => 'Person',
        'due_at' => 'Fällig am',
        'fulfilled_at' => 'Nachgewiesen am',
        'proof' => 'Nachweis',
        'state' => 'Zustand',
        'source' => 'Herkunft',
        'requirements_count' => 'Zuordnungen',
        'assignments_count' => 'Soll-Einträge',
    ],

    'action' => [
        'create_course' => 'Kurs anlegen',
        'create_requirement' => 'Zuordnung anlegen',
        'create_assignment' => 'Soll-Eintrag anlegen',
        'create_version' => 'Kursversion anlegen',
        'sync_assignments' => 'Soll aktualisieren',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'delete' => 'Löschen',
        'show' => 'Ansehen',
        'back' => 'Zurück',
    ],

    'filter' => [
        'all' => 'Alle',
        'mandatory_only' => 'Nur Pflichtschulungen',
        'state' => 'Zustand',
        'subject_kind' => 'Zielgruppe',
    ],

    'kpi' => [
        'mandatory' => 'Pflichtschulungen',
        'active_requirements' => 'Aktive Zuordnungen',
        'overdue' => 'Überfällig',
    ],

    'empty' => [
        'courses' => 'Noch kein Kurs im Katalog.',
        'versions' => 'Noch keine Kursversion angelegt.',
        'requirements' => 'Noch keine Pflichtzuordnung angelegt.',
        'assignments' => 'Noch kein Schulungs-Soll vorhanden.',
    ],

    'hint' => [
        'cost_informational' => 'Kosten dienen nur der Information — es entsteht keine Buchung und kein Beleg.',
        'instruction_course' => 'Mit Kursbezug zählt diese Teilnahme als Nachweis für das Schulungs-Soll.',
        'no_second_guard' => 'Das Schulungs-Soll meldet und wertet aus; gesperrt wird über den Qualifikationsstatus.',
        'proof_in_register' => 'Nachweise werden ausschließlich als Unterweisung im Arbeitsschutz-Register erfasst.',
        'sync' => 'Der Abgleich erzeugt fehlende Soll-Einträge und entfernt nicht mehr geforderte ohne Nachweis.',
    ],

    'confirm' => [
        'delete_course' => 'Kurs löschen?',
        'delete_version' => 'Kursversion löschen?',
        'delete_requirement' => 'Zuordnung löschen?',
        'delete_assignment' => 'Soll-Eintrag löschen?',
    ],

    'flash' => [
        'course_created' => 'Kurs wurde angelegt.',
        'course_updated' => 'Kurs wurde aktualisiert.',
        'course_deleted' => 'Kurs wurde gelöscht.',
        'version_created' => 'Kursversion wurde angelegt.',
        'version_deleted' => 'Kursversion wurde gelöscht.',
        'requirement_created' => 'Zuordnung wurde angelegt.',
        'requirement_updated' => 'Zuordnung wurde aktualisiert.',
        'requirement_deleted' => 'Zuordnung wurde gelöscht.',
        'assignment_created' => 'Soll-Eintrag wurde angelegt.',
        'assignment_deleted' => 'Soll-Eintrag wurde gelöscht.',
        'assignments_synced' => 'Soll abgeglichen: :created neu, :removed entfernt.',
    ],

    'error' => [
        'delete_with_proof' => 'Zu diesem Kurs gibt es Nachweise — er kann nur deaktiviert werden.',
        'delete_last_version' => 'Die letzte Kursversion kann nicht gelöscht werden.',
        'delete_version_in_use' => 'Diese Kursversion ist in einer Unterweisung nachgewiesen und bleibt bestehen.',
    ],

    'report' => [
        'title' => 'Schulungs-Auswertung',
        'nav' => 'Schulungen',
        'subtitle' => 'Erfüllungsgrad je Team, Rolle und Kurs zum Stichtag — Grundlage des Kompetenznachweises.',
        'total' => 'Gesamt',
        'team' => 'Team',
        'role' => 'Rolle',
        'course' => 'Kurs',
        'no_team' => 'Ohne Team',
        'no_role' => 'Ohne Rolle',
        'rate' => 'Erfüllungsgrad',
        'rate_by_team' => 'Erfüllungsgrad je Team',
        'rate_by_course' => 'Erfüllungsgrad je Kurs',
        'by_team' => 'Nach Team',
        'by_role' => 'Nach Rolle',
        'by_course' => 'Nach Kurs',
        'kpi' => [
            'assignments' => 'Soll-Einträge',
            'fulfilled' => 'Erfüllt',
            'due' => 'Fällig',
            'overdue' => 'Überfällig',
            'rate' => 'Erfüllungsgrad',
        ],
        'empty' => 'Für den gewählten Filter gibt es kein Schulungs-Soll.',
    ],
];
