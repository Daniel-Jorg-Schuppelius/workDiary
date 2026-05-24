<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : onboarding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'page' => [
        'title' => 'Onboarding',
        'heading' => 'Onboarding-Checkliste',
        'progress_label' => 'Fortschritt',
        'progress_summary' => 'Pflichtschritte: :done von :total (:percent %)',
        'badge_required' => 'Pflicht',
        'badge_recommended' => 'Empfohlen',
        'badge_done' => 'Erledigt',
        'badge_open' => 'Offen',
        'badge_skipped' => 'Übersprungen',
    ],

    'widget' => [
        'title' => 'Onboarding einrichten',
        'subtitle' => ':done von :total Pflichtschritten erledigt',
        'open_link' => 'Onboarding öffnen',
        'dismiss' => 'Widget ausblenden',
        'dismissed_at' => 'Widget ausgeblendet: :date',
        'complete_headline' => 'Alle Pflichtschritte erledigt',
        'complete_subtitle' => 'Die Organisation ist startbereit.',
        'open_steps' => '{0} Keine offenen Schritte|{1} :count offener Schritt|[2,*] :count offene Schritte',
    ],

    'action' => [
        'skip' => 'Überspringen',
        'skip_placeholder' => 'Begründung für Überspringen',
        'flash_skipped' => 'Onboarding-Schritt wurde übersprungen.',
        'flash_dismissed' => 'Onboarding-Widget wurde ausgeblendet.',
        'error_step_not_skippable' => 'Dieser Onboarding-Schritt kann nicht übersprungen werden.',
    ],

    'step' => [
        'org.profile' => [
            'title' => 'Organisationsdaten vervollständigen',
            'description' => 'Pflege Name, Zeitzone und lokale Grundeinstellungen der Organisation.',
            'link' => 'Organisation öffnen',
        ],
        'org.branch_profile' => [
            'title' => 'Branchenprofil wählen',
            'description' => 'Wähle ein Branchenprofil, damit passende Defaults für Klassifikationen bereitstehen.',
            'link' => 'Branchenprofile öffnen',
        ],
        'users.invite' => [
            'title' => 'Erste Nutzer einladen',
            'description' => 'Lade mindestens eine weitere aktive Person in deine Organisation ein.',
            'link' => 'Mitglieder öffnen',
        ],
        'roles.check' => [
            'title' => 'Rollen prüfen',
            'description' => 'Prüfe, dass mindestens ein Org-Admin und ein Operator zugewiesen sind.',
            'link' => 'Rechteverwaltung öffnen',
        ],
        'classification.check' => [
            'title' => 'Klassifikationen prüfen',
            'description' => 'Bestätige oder überschreibe mindestens eine Klassifikationsdomäne für die Organisation.',
            'link' => 'Klassifikationen öffnen',
        ],
        'customer.first' => [
            'title' => 'Ersten Kunden anlegen',
            'description' => 'Lege den ersten Kunden manuell an oder nutze den CSV-Import.',
            'link' => 'Kunden öffnen',
        ],
        'work.first' => [
            'title' => 'Erstes Projekt oder Auftrag',
            'description' => 'Erzeuge ein erstes Projekt oder starte den ersten Auftrag im Tagebuch.',
            'link' => 'Projekte öffnen',
        ],
        'time.first' => [
            'title' => 'Erste Zeiterfassung',
            'description' => 'Erfasse mindestens einen Zeiteintrag, um die Arbeitszeiterfassung zu aktivieren.',
            'link' => 'Zeiterfassung öffnen',
        ],
        'protocol.first_signed' => [
            'title' => 'Erstes Protokoll signieren',
            'description' => 'Erstelle ein Protokoll und schließe die Signatur ab.',
            'link' => 'Tagebuch öffnen',
        ],
        'backup.heartbeat' => [
            'title' => 'Backup-Heartbeat',
            'description' => 'Konfiguriere den Backup-Lauf so, dass regelmäßig erfolgreiche Heartbeats geschrieben werden.',
            'link' => 'Audit-Log öffnen',
        ],
    ],
];
