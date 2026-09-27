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
        'org' => [
            'profile' => [
                'title' => 'Organisationsdaten vervollständigen',
                'description' => 'Pflegen Sie Name, Zeitzone und lokale Grundeinstellungen der Organisation.',
                'link' => 'Organisation öffnen',
            ],
            'branch_profile' => [
                'title' => 'Branchenprofil wählen',
                'description' => 'Wählen Sie ein Branchenprofil, damit passende Defaults für Klassifikationen bereitstehen.',
                'link' => 'Branchenprofile öffnen',
            ],
            'scope' => [
                'title' => 'Funktionsumfang wählen',
                'description' => 'Wählen Sie ein Funktionsumfang-Preset oder passen Sie die aktiven Module an — nicht Benötigtes bleibt ausgeblendet, ohne dass Daten verloren gehen.',
                'link' => 'Funktionsumfang öffnen',
            ],
            'workspaces' => [
                'title' => 'Arbeitsbereiche einrichten',
                'description' => 'Legen Sie fest, welche Arbeitsbereiche im Umschalter erscheinen und welcher der Standard ist — jede Person kann jederzeit wechseln.',
                'link' => 'Arbeitsbereiche öffnen',
            ],
        ],
        'users' => [
            'invite' => [
                'title' => 'Erste Nutzer einladen',
                'description' => 'Laden Sie mindestens eine weitere aktive Person in Ihre Organisation ein.',
                'link' => 'Mitglieder öffnen',
            ],
        ],
        'roles' => [
            'check' => [
                'title' => 'Rollen prüfen',
                'description' => 'Prüfen Sie, dass mindestens ein Org-Admin und ein Operator zugewiesen sind.',
                'link' => 'Rechteverwaltung öffnen',
            ],
        ],
        'classification' => [
            'check' => [
                'title' => 'Klassifikationen prüfen',
                'description' => 'Bestätigen oder überschreiben Sie mindestens eine Klassifikationsdomäne für die Organisation.',
                'link' => 'Klassifikationen öffnen',
            ],
        ],
        'customer' => [
            'first' => [
                'title' => 'Ersten Kunden anlegen',
                'description' => 'Legen Sie den ersten Kunden manuell an oder nutzen Sie den CSV-Import.',
                'link' => 'Kunden öffnen',
            ],
        ],
        'work' => [
            'first' => [
                'title' => 'Erstes Projekt oder Auftrag',
                'description' => 'Erzeugen Sie ein erstes Projekt oder starten Sie den ersten Auftrag im Auftragsbuch.',
                'link' => 'Projekte öffnen',
            ],
        ],
        'time' => [
            'first' => [
                'title' => 'Erste Zeiterfassung',
                'description' => 'Erfassen Sie mindestens einen Zeiteintrag, um die Arbeitszeiterfassung zu aktivieren.',
                'link' => 'Zeiterfassung öffnen',
            ],
        ],
        'protocol' => [
            'first_signed' => [
                'title' => 'Erstes Protokoll signieren',
                'description' => 'Erstellen Sie ein Protokoll und schließen Sie die Signatur ab.',
                'link' => 'Auftragsbuch öffnen',
            ],
        ],
        'backup' => [
            'heartbeat' => [
                'title' => 'Backup-Heartbeat',
                'description' => 'Konfiguriere den Backup-Lauf so, dass regelmäßig erfolgreiche Heartbeats geschrieben werden.',
                'link' => 'Audit-Log öffnen',
            ],
        ],
    ],
    // Persönlicher Einstieg je Rolle (MVP-911).
    'personal' => [
        'title' => 'Mein Einstieg',
        'description' => 'Ein paar Schritte, um mit WorkDiary in Ihrer Rolle gut zu starten. Erledigtes erkennt WorkDiary selbst; Schritte ohne Merkmal haken Sie ab.',
        'progress' => ':done von :total Schritten erledigt',
        'open' => 'Einstieg öffnen',
        'go' => 'Öffnen',
        'mark_done' => 'Erledigt',
        'dismiss' => 'Einstieg ausblenden',
        'marked' => 'Schritt als erledigt markiert.',
        'dismissed' => 'Einstieg ausgeblendet; Sie finden ihn weiter unter „Mein Einstieg“.',
        'step' => [
            'profile' => [
                'two_factor' => [
                    'title' => 'Zweiten Faktor einrichten',
                    'hint' => 'Schützt Ihr Konto mit App, Passkey oder Sicherheitsschlüssel.',
                ],
                'startpage' => [
                    'title' => 'Startseite wählen',
                    'hint' => 'Legen Sie fest, womit WorkDiary nach der Anmeldung öffnet.',
                ],
            ],
            'dashboard' => [
                'customize' => [
                    'title' => 'Dashboard anpassen',
                    'hint' => 'Blenden Sie die Kacheln ein, die Sie täglich brauchen.',
                ],
            ],
            'time' => [
                'first' => [
                    'title' => 'Erste Zeit erfassen',
                    'hint' => 'Buchen Sie eine Arbeitszeit, zum Beispiel über „Heute“.',
                ],
            ],
            'attendance' => [
                'first' => [
                    'title' => 'Anwesenheit stempeln',
                    'hint' => 'Kommen und Gehen einmal erfassen.',
                ],
            ],
            'expense' => [
                'first' => [
                    'title' => 'Spesen erfassen',
                    'hint' => 'Einen Beleg oder eine Reise als Spesen erfassen.',
                ],
            ],
            'diary' => [
                'first' => [
                    'title' => 'Ersten Auftrag anlegen',
                    'hint' => 'Einen Auftrag anlegen und zuweisen.',
                ],
            ],
            'invoice' => [
                'first' => [
                    'title' => 'Erste Rechnung anlegen',
                    'hint' => 'Eine Rechnung als Entwurf anlegen.',
                ],
            ],
            'reports' => [
                'accounting' => [
                    'title' => 'Finanzberichte kennenlernen',
                    'hint' => 'Einmal die Übersicht der Finanzberichte öffnen.',
                ],
            ],
            'org' => [
                'checklist' => [
                    'title' => 'Einrichtung der Organisation',
                    'hint' => 'Die Checkliste der Organisation durchgehen.',
                ],
            ],
            'help' => [
                'center' => [
                    'title' => 'Hilfecenter öffnen',
                    'hint' => 'Anleitungen und Antworten zu Ihren Aufgaben.',
                ],
            ],
        ],
    ],
];
