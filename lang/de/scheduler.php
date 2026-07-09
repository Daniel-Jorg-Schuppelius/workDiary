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
