<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : operations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Betriebsaufgaben',
        'subtitle' => 'Updates, Backups, Abläufe und Störungen — priorisiert und nachverfolgbar.',
        'widget' => 'Offene Betriebsaufgaben',
    ],
    'type' => [
        'backup_overdue' => 'Backup überfällig',
        'backup_failed' => 'Backup fehlgeschlagen',
        'restore_test_overdue' => 'Restore-Test überfällig',
        'update_available' => 'Update verfügbar',
        'update_security' => 'Sicherheitsupdate',
        'license_expiring' => 'Lizenzablauf',
        'credential_expiring' => 'Zugangs-/Token-Ablauf',
        'connection_failing' => 'Verbindungsstörung',
        'component_eol' => 'Komponente ohne Support',
        'plugin_disabled' => 'Plugin deaktiviert',
        'scheduler_overdue' => 'Geplante Aufgabe überfällig',
        'maintenance_scheduled' => 'Wartungsfenster',
        'config_missing' => 'Konfiguration fehlt',
        'support_grant_open' => 'Offene Supportfreigabe',
        'problem_report_open' => 'Offene Fehlermeldung',
    ],
    'severity' => [
        'info' => 'Hinweis',
        'warning' => 'Warnung',
        'critical' => 'Kritisch',
    ],
    'status' => [
        'open' => 'Offen',
        'snoozed' => 'Zurückgestellt',
        'delegated' => 'Delegiert',
        'ignored' => 'Ignoriert',
        'done' => 'Erledigt',
        'resolved' => 'Von selbst behoben',
    ],
    'field' => [
        'task' => 'Aufgabe',
        'severity' => 'Dringlichkeit',
        'status' => 'Status',
        'first_seen' => 'Zuerst erkannt',
        'last_seen' => 'Zuletzt bestätigt',
        'assignee' => 'Zuständig',
        'actions' => 'Aktionen',
        'note' => 'Begründung',
        'snooze_until' => 'Zurückstellen bis',
        'system_wide' => 'Installationsweit',
    ],
    'action' => [
        'done' => 'Erledigt',
        'snooze' => 'Zurückstellen',
        'delegate' => 'Delegieren',
        'ignore' => 'Ignorieren',
        'reopen' => 'Wieder öffnen',
        'open_link' => 'Zur Ursache',
    ],
    'task' => [
        'backup_overdue' => 'Letztes Backup ist :hours Stunden alt (Schwelle :threshold h).',
        'backup_failed' => 'Backup-Prüfung fehlgeschlagen: :reason',
        'restore_test_overdue' => 'Letzter Restore-Test liegt :days Tage zurück (Schwelle :threshold Tage).',
        'restore_test_missing' => 'Es wurde noch nie ein Restore-Test protokolliert.',
        'update_available' => 'Update für :component verfügbar: :installed → :available.',
        'update_security' => 'Sicherheitsupdate für :component: :installed → :available (:classification).',
        'license_expiring' => 'Lizenz läuft am :date ab (:days Tage verbleibend).',
        'credential_expiring' => ':kind „:name" läuft am :date ab.',
        'connection_failing' => 'Verbindung „:name" (:kind) gestört: :error',
        'component_eol' => ':component :version wird seit :date nicht mehr unterstützt.',
        'plugin_disabled' => 'Plugin „:plugin" wurde nach :failures Fehlern automatisch deaktiviert.',
        'scheduler_overdue' => 'Geplante Aufgabe „:job" ist überfällig (fällig :due).',
        'maintenance_scheduled' => 'Wartungsfenster :from – :to::scope',
        'support_grant_open' => 'Supportfreigabe für :grantee aktiv bis :until.',
        'problem_report_open' => 'Fehlermeldung :reference von :name wartet auf Bearbeitung.',
        'problem_report_summary' => ':count offene Fehlermeldung(en) warten auf Bearbeitung.',
        'support_grant_summary' => ':count aktive Supportfreigabe(n) — prüfen und bei Bedarf widerrufen.',
    ],
    'filter' => [
        'active' => 'Aktive Aufgaben',
        'all_severities' => 'Alle Dringlichkeiten',
        'all_types' => 'Alle Typen',
    ],
    'empty' => [
        'title' => 'Keine Betriebsaufgaben',
        'message' => 'Aktuell ist nichts zu tun — alle Betriebsaufgaben sind erledigt oder von selbst behoben.',
    ],
    'hint' => [
        'auto_disabled_after' => 'Automatisch deaktiviert nach :failures Fehlversuchen.',
        'no_contact_since' => 'Kein Kontakt seit :date.',
    ],
    'flash' => [
        'done' => 'Aufgabe als erledigt markiert.',
        'snoozed' => 'Aufgabe zurückgestellt bis :date.',
        'delegated' => 'Aufgabe delegiert.',
        'ignored' => 'Aufgabe ignoriert.',
        'reopened' => 'Aufgabe wieder geöffnet.',
    ],
];
