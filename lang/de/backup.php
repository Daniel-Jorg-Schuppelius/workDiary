<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'status' => 'Backup & Restore',
        'log_restore_test' => 'Restore-Test protokollieren',
    ],

    'subtitle' => 'Status der externen Sicherungen je Quelle, Frische-Warnungen und Register der durchgeführten Restore-Tests.',

    'section' => [
        'last_per_source' => 'Letzte Sicherung je Quelle',
        'restore_register' => 'Restore-Test-Register',
        'restore_test' => 'Restore-Test',
        'retention' => 'Aufbewahrung (Retention)',
    ],

    'field' => [
        'source' => 'Quelle',
        'occurred_at' => 'Zeitpunkt',
        'age' => 'Alter',
        'size' => 'Größe',
        'manifest_hash' => 'Manifest-Hash',
        'state' => 'Status',
        'tested_on' => 'Getestet am',
        'result' => 'Ergebnis',
        'scope' => 'Umfang',
        'restored_size' => 'Wiederhergestellt',
        'restored_size_bytes' => 'Wiederhergestellte Größe (Bytes)',
        'duration' => 'Dauer',
        'duration_minutes' => 'Dauer (Minuten)',
        'next_due' => 'Nächste Fälligkeit',
        'performed_by' => 'Durchgeführt von',
        'notes' => 'Notiz',
        'last_passed' => 'Letzter erfolgreicher Test',
        'no_passed_test' => 'Noch kein erfolgreicher Restore-Test protokolliert',
    ],

    'badge' => [
        'fresh' => 'aktuell',
        'overdue' => 'überfällig',
    ],

    'value' => [
        'hours' => ':n h',
        'minutes' => ':n min',
        'days_ago' => 'vor :n Tagen',
    ],

    'action' => [
        'log_restore_test' => 'Restore-Test protokollieren',
        'save' => 'Speichern',
        'open_help' => 'Backup-Handbuch öffnen',
    ],

    'warn' => [
        'no_heartbeat_title' => 'Kein Backup registriert',
        'no_heartbeat_body' => 'Es ist bisher kein Backup-Heartbeat eingegangen. Prüfe, ob das externe Backup-Skript läuft und den Heartbeat-Endpoint mit gültigem Token aufruft.',
        'overdue_title' => 'Backup überfällig',
        'overdue_body' => 'Mindestens eine Quelle hat seit mehr als :hours Stunden keinen Heartbeat gemeldet. Letzte Sicherung prüfen.',
        'restore_overdue_title' => 'Überfälliger Restore-Test',
        'restore_overdue_body' => 'Seit mehr als :days Tagen wurde kein erfolgreicher Restore-Test protokolliert. Bitte einen Wiederherstellungs-Test durchführen und hier eintragen.',
    ],

    'hint' => [
        'freshness' => 'Eine Quelle gilt als überfällig, wenn ihr jüngster Heartbeat älter als :hours Stunden ist (konfigurierbar über BACKUP_HEARTBEAT_FRESHNESS_HOURS).',
        'register_manual' => 'Dies ist ein nachvollziehbares Register. Die eigentliche Wiederherstellung erfolgt manuell oder per Skript außerhalb von WorkDiary — die automatisierte Restore-Ausführung ist bewusst nicht Teil dieser Seite.',
        'retention' => 'Empfohlene Aufbewahrung: 7 tägliche, 4 wöchentliche, 12 monatliche Sicherungen (3-2-1-Regel). Mindestens ein Offsite-Backup an einem anderen Standort.',
        'see_docs' => 'Details zur Strategie, zum Heartbeat und zur Schritt-für-Schritt-Wiederherstellung stehen in docs/backup-restore.md.',
    ],

    'empty' => [
        'no_heartbeat' => 'Kein Backup registriert',
        'no_heartbeat_hint' => 'Sobald das externe Backup-Skript einen Heartbeat sendet, erscheint hier die letzte Sicherung je Quelle.',
        'no_restore_tests' => 'Noch keine Restore-Tests protokolliert',
    ],

    'placeholder' => [
        'source' => 'z. B. nightly, offsite, weekly-full',
        'scope' => 'z. B. DB+Storage, nur Anhänge',
        'notes' => 'Beobachtungen, Auflagen, Abweichungen …',
    ],

    'flash' => [
        'restore_test_logged' => 'Restore-Test protokolliert.',
    ],

    'generated_at' => 'Stand: :at',
];
