<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scheduler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Geplante Aufgaben',
        'subtitle' => 'Registry-Jobs pausieren, umplanen und überwachen — ohne Codeänderung.',
        'help' => 'Nur registrierte Jobs, nur erlaubte Zeiten',
        'help_text' => 'Alle Jobs stammen aus der serverseitigen Job-Registry. Umplanungen sind auf die je Job erlaubten Intervalle begrenzt; Änderungen werden auditiert und wirken ab dem nächsten Scheduler-Tick.',
        'reschedule' => 'Job umplanen',
    ],
    'field' => [
        'job' => 'Job',
        'plan' => 'Zeitplan',
        'last_run' => 'Letzter Lauf',
        'next_due' => 'Nächste Fälligkeit',
        'failures' => 'Fehler in Folge',
        'actions' => 'Aktionen',
        'cadence_type' => 'Intervall',
        'time' => 'Uhrzeit',
        'day' => 'Tag',
        'expression' => 'Cron-Ausdruck',
    ],
    'action' => [
        'reschedule' => 'Umplanen',
        'pause' => 'Pausieren',
        'resume' => 'Fortsetzen',
        'reset' => 'Auf Standard zurücksetzen',
        'test_run' => 'Testlauf starten',
        'save' => 'Speichern',
    ],
    'state' => [
        'paused' => 'Pausiert',
        'success' => 'Erfolgreich',
        'failed' => 'Fehlgeschlagen',
        'never_ran' => 'Noch nie gelaufen',
    ],
    'source' => [
        'default' => 'Standardplan',
        'setting' => 'Aus Einstellung',
        'override' => 'Manuell umgeplant',
    ],
    'cadence' => [
        'everyMinute' => 'Jede Minute',
        'everyFiveMinutes' => 'Alle 5 Minuten',
        'everyFifteenMinutes' => 'Alle 15 Minuten',
        'everyThirtyMinutes' => 'Alle 30 Minuten',
        'hourly' => 'Stündlich',
        'dailyAt' => 'Täglich um',
        'weeklyOn' => 'Wöchentlich am',
        'monthlyOn' => 'Monatlich am',
        'cron' => 'Cron-Ausdruck',
    ],
    'criticality' => [
        'core' => 'Kernbetrieb',
        'integration' => 'Integration',
        'housekeeping' => 'Aufräumen',
    ],
    // Lesbare Job-Namen (Registry-Keys, geschachtelt wegen Punkt-Notation);
    // neue Registry-Jobs hier in allen Locales ergänzen — sonst Fallback = Key.
    'job' => [
        'archive' => ['run' => 'Archivierungslauf'],
        'attendance' => ['close_open' => 'Offene Stempelungen schließen'],
        'backup' => ['check_restore' => 'Backup-Prüfung'],
        'catalog' => ['fetch_due' => 'Katalogquellen abrufen'],
        'chat' => [
            'send_reminders' => 'Chat-Erinnerungen versenden',
            'send_scheduled' => 'Geplante Chat-Nachrichten senden',
        ],
        'events' => [
            'check_certificates' => 'Zertifikatsablauf prüfen',
            'dispatch_reminders' => 'Event-Erinnerungen versenden',
            'materialize_recurrences' => 'Wiederkehrende Events anlegen',
        ],
        'integration' => ['purge_inbox' => 'Integrations-Inbox bereinigen'],
        'lexoffice' => [
            'sync_articles' => 'Lexoffice-Artikel synchronisieren',
            'sync_contacts' => 'Lexoffice-Kontakte synchronisieren',
            'sync_vouchers' => 'Lexoffice-Belege synchronisieren',
        ],
        'location' => ['purge_points' => 'Standort-Rohpunkte bereinigen'],
        'maintenance' => ['scan_due' => 'Wartungspläne auf Fälligkeit prüfen'],
        'notifications' => ['scan_deadlines' => 'Fristen prüfen und erinnern'],
        'openproject' => [
            'import' => 'OpenProject-Import',
            'push' => 'Zeiten nach OpenProject übertragen',
        ],
        'operations' => ['scan' => 'Betriebsaufgaben abgleichen'],
        'payroll' => ['import_minimum_wages' => 'EU-Mindestlöhne importieren'],
        'plans' => ['purge' => 'Downgrade-Daten bereinigen'],
        'plugin' => ['healthcheck' => 'Plugin-Healthcheck'],
        'privacy' => [
            'deadlines' => 'Betroffenenanfragen-Fristen prüfen',
            'retention_scan' => 'Löschfristen-Scan',
        ],
        'recurrence' => ['generate' => 'Wiederkehrende Aufträge erzeugen'],
        'remote' => ['sync_sessions' => 'Fernwartungs-Sitzungen importieren'],
        'scheduler' => ['watchdog' => 'Scheduler-Überwachung'],
        'security' => ['advisories_pull' => 'Sicherheitshinweise abrufen'],
        'tickets' => ['scan_sla_breaches' => 'SLA-Verletzungen prüfen'],
        'todoist' => ['sync' => 'Todoist-Abgleich'],
        'toggl' => ['import' => 'Toggl-Import'],
        'updates' => ['check' => 'Update-Prüfung'],
    ],
    'hint' => [
        'time' => 'Nur für tägliche/wöchentliche/monatliche Pläne.',
        'day' => 'Wochentag 0–6 (0 = Sonntag) bzw. Monatstag 1–31.',
        'expression' => 'Nur für Betreiber: Minute Stunde Tag Monat Wochentag.',
        'allowlist' => 'Erwartete Laufzeit ca. :runtime Min. Der Job läuft mit Überschneidungsschutz; zu enge Intervalle werden serverseitig abgelehnt.',
    ],
    'flash' => [
        'rescheduled' => 'Job :job wurde umgeplant.',
        'paused' => 'Job :job wurde pausiert.',
        'resumed' => 'Job :job wurde fortgesetzt.',
        'reset' => 'Job :job nutzt wieder den Standardplan.',
        'test_run_queued' => 'Testlauf für :job wurde in die Warteschlange gestellt.',
        'test_run_cooldown' => 'Bitte warten — je Job ist nur ein Testlauf alle :minutes Minuten möglich.',
    ],
];
