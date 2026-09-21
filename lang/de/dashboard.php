<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dashboard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'width' => [
        'half' => 'Halbe Breite',
        'full' => 'Volle Breite',
    ],

    'group' => [
        'overview' => 'Überblick',
        'time' => 'Zeit',
        'tasks' => 'Aufgaben',
        'activity' => 'Aktivität',
        'deadlines' => 'Fristen',
        'finance' => 'Finanzen',
        'operations' => 'Betrieb',
    ],

    'widget' => [
        'personal_kpis' => [
            'description' => 'Offene Einträge, laufende Arbeiten, anstehende Schichten und Notdienste.',
        ],
        'team_kpis' => [
            'description' => 'Offene und laufende Einträge des Teams, heute Archiviertes, Mitarbeiterzahl.',
        ],
        'today_shifts' => [
            'description' => 'Ihre Schichten des heutigen Tages.',
        ],
        'upcoming_shifts' => [
            'description' => 'Die nächsten Rufbereitschaften und Schichten.',
        ],
        'emergencies' => [
            'description' => 'Anstehende Notdiensteinsätze.',
        ],
        'scheduled_shifts' => [
            'description' => 'Dienstplan der nächsten sieben Tage.',
        ],
        'open_issues' => [
            'description' => 'Offene Punkte, die Ihnen zugewiesen sind — nach Fälligkeit.',
        ],
        'recent_entries' => [
            'description' => 'Ihre zuletzt bearbeiteten Einträge.',
        ],
        'recent_comments' => [
            'description' => 'Neue Kommentare auf Ihren Einträgen.',
        ],
        'recent_attachments' => [
            'description' => 'Neue Anhänge auf Ihren Einträgen.',
        ],
        'team_activity' => [
            'description' => 'Die letzten Kommentare im Team.',
        ],
        'finance' => [
            'description' => 'Spesen und Reisen des laufenden Monats, für Genehmigende zusätzlich der offene Stapel.',
        ],
        'vacation' => [
            'description' => 'Offene Urlaubsanträge und genehmigte Tage des Jahres.',
        ],
        'onboarding' => [
            'description' => 'Fortschritt der Einrichtungs-Checkliste.',
        ],
        'attendance_clock' => [
            'description' => 'Ein- und Ausstempeln, Pause und Zwischenstatus.',
        ],
        'bookmarks' => [
            'description' => 'Ihre gespeicherten Lesezeichen.',
        ],
        'data_protection' => [
            'description' => 'Überfällige Prüfungen im Verzeichnis und offene Betroffenenanfragen.',
        ],
        'operations_tasks' => [
            'description' => 'Offene Betriebsaufgaben nach Dringlichkeit.',
        ],
        'stopwatch' => [
            'description' => 'Laufende Projektzeit mit Projekt und Beschreibung.',
        ],
        'flex_balance' => [
            'description' => 'Gleitzeit-Saldo des zuletzt abgerechneten Monats mit Ampel.',
        ],
        'time_accounts' => [
            'description' => 'Salden Ihrer Zeitkonten (Überstunden, Sonderkonten).',
        ],
        'time_corrections' => [
            'description' => 'Ihre Korrekturanträge, die noch in Arbeit oder eingereicht sind.',
        ],
        'reminders' => [
            'description' => 'Fällige Aufgaben aus Spesen, Reisen und Urlaub — dieselben wie unter der Glocke.',
        ],
        'kanban_status' => [
            'description' => 'Wie viele Ihrer Aufträge in welcher Kanban-Spalte stehen.',
        ],
        'service_tickets' => [
            'description' => 'Offene Tickets, die Ihnen zugewiesen sind.',
        ],
        'chat_unread' => [
            'description' => 'Ungelesene Nachrichten je Kanal.',
        ],
        'approvals' => [
            'description' => 'Spesen und Urlaubsanträge, die auf Ihre Entscheidung warten.',
        ],
        'asset_compliance' => [
            'description' => 'Überfällige und bald fällige Prüfungen aus dem Prüfkalender.',
        ],
        'asset_blocks' => [
            'description' => 'Objekte, die aktuell gesperrt sind, mit Sperrgrund.',
        ],
        'contract_deadlines' => [
            'description' => 'Offene Vertragspflichten und Fristen der nächsten Wochen.',
        ],
        'leasing_deadlines' => [
            'description' => 'Kündigungs-, Rückgabe- und Verlängerungsfristen aus Leasingakten.',
        ],
        'safety_due' => [
            'description' => 'Anstehende Prüfungen von Gefährdungsbeurteilungen und Vorsorgeterminen.',
        ],
        'training_due' => [
            'description' => 'Ihre offenen Schulungs- und Unterweisungspflichten.',
        ],
        'learning_due' => [
            'description' => 'Ihre offenen Schulungen der Lernplattform — überfällige zuerst.',
        ],
        'learning_grading_queue' => [
            'description' => 'Abgaben, Aufsätze und Lernzeit-Freigaben, die auf Bewertung warten.',
        ],
        'open_times' => [
            'description' => 'Abrechenbare Zeiten, die noch in keiner Rechnung stecken.',
        ],
        'open_items' => [
            'description' => 'Offene Forderungen und Verbindlichkeiten samt überfälligem Anteil.',
        ],
        'tax_filings' => [
            'description' => 'Anstehende Melde- und Abgabetermine der Buchhaltung.',
        ],
        'integration_inbox' => [
            'description' => 'Importierte Positionen, die noch keiner Zuordnung haben.',
        ],
        'backup_status' => [
            'description' => 'Wie frisch die Sicherungen je Quelle sind.',
        ],
        'plugin_health' => [
            'description' => 'Plugins, deren letzter Gesundheitscheck fehlschlug.',
        ],
    ],

    'preset' => [
        'classic' => [
            'label' => 'Klassisches Dashboard',
            'description' => 'Kennzahlen und Lesezeichen oben, darunter die vier Bereiche Überblick, Aufgaben, Aktivität und Finanzen — das Dashboard wie vor dem Kachel-Umbau, ergänzt um die Stempeluhr.',
        ],
    ],
];
