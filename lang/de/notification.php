<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : notification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'center' => 'Benachrichtigungen',
        'center_subtitle' => 'Deine In-App-Benachrichtigungen — gelesen und ungelesen.',
        'empty' => 'Keine Benachrichtigungen.',
        'empty_message' => 'Sobald dich ein Ereignis betrifft, erscheint es hier.',
        'rules' => 'Benachrichtigungsregeln',
        'rules_subtitle' => 'Regeln pro Ereignistyp: Kanäle, Empfänger und Eskalation.',
        'rules_help' => 'Wie funktionieren Benachrichtigungsregeln?',
        'rules_help_text' => 'Pro Ereignistyp legt die Regel fest, ob und über welche Kanäle benachrichtigt wird und wer die Empfänger sind (betroffene Person, Rollen, feste Personen). Ohne gespeicherte Regel gilt der angezeigte Standard. Eskalation: bleibt ein überfälliges Ereignis nach der eingestellten Zeit unerledigt, wird zusätzlich die Eskalationsrolle benachrichtigt. Weitere Eskalationsstufen (2/3) benachrichtigen nach je eigener Frist zusätzliche Empfängergruppen. Der Kanal „Kalender“ trägt terminbezogene Ereignisse in die verbundenen Kalender der Organisation ein (CalDAV/Microsoft 365/Google).',
        'edit_rule' => 'Benachrichtigungsregel bearbeiten',
        'preferences' => 'Benachrichtigungen',
    ],

    'field' => [
        'event' => 'Ereignis',
        'enabled' => 'Aktiv',
        'rule_enabled' => 'Benachrichtigungen für dieses Ereignis aktiv',
        'channels' => 'Kanäle',
        'recipients' => 'Empfänger',
        'affected_user' => 'Betroffene Person',
        'notify_affected_help' => 'Betroffene Person benachrichtigen (z. B. zugewiesene oder antragstellende Person)',
        'recipient_roles' => 'Empfänger-Rollen',
        'recipient_users' => 'Zusätzliche feste Empfänger',
        'fixed_users' => 'feste Empfänger',
        'escalation' => 'Eskalation',
        'escalation_enabled' => 'Eskalation aktiv',
        'escalation_help' => 'Bleibt das Ereignis nach der Erst-Benachrichtigung die eingestellte Zeit unerledigt, wird zusätzlich die Eskalationsrolle benachrichtigt.',
        'escalation_unsupported' => 'Eskalation ist nur für Überfälligkeits-Ereignisse verfügbar.',
        'escalate_after_hours' => 'Eskalieren nach (Stunden)',
        'escalation_role' => 'Eskalationsrolle',
        'escalation_summary' => 'nach :hours h an :role',
        'escalation_ladder_help' => 'Optionale weitere Stufe: bleibt das Ereignis nach dem Versand der vorherigen Eskalationsstufe die eingestellte Zeit unerledigt, wird zusätzlich diese Empfängergruppe benachrichtigt.',
        'escalation_level2' => 'Eskalationsstufe 2',
        'escalation_level3' => 'Eskalationsstufe 3',
        'escalation_level_after_hours' => 'Nach weiteren Stunden',
        'escalation_level_roles' => 'Empfänger-Rollen der Stufe',
        'escalation_level_users' => 'Feste Empfänger der Stufe',
        'escalation_level_summary' => 'Stufe :level: +:hours h',
        'default_rule' => 'Standard (noch nicht angepasst)',
        'unread' => 'neu',
        'yes' => 'Ja',
        'no' => 'Nein',
        'mail_enabled' => 'E-Mail-Benachrichtigungen erhalten',
        'quiet_from' => 'Ruhezeit von',
        'quiet_to' => 'Ruhezeit bis',
        'preferences_help' => 'In-App-Benachrichtigungen werden immer gesammelt. E-Mail (und Push) lassen sich abschalten; während der Ruhezeit werden keine E-Mails/Push gesendet.',
    ],

    'action' => [
        'mark_read' => 'Als gelesen markieren',
        'mark_all_read' => 'Alle als gelesen markieren',
        'delete' => 'Löschen',
        'delete_read' => 'Gelesene löschen',
        'open' => 'Öffnen',
        'show_all' => 'Alle anzeigen',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
    ],

    'confirm' => [
        'delete_read' => 'Alle gelesenen Benachrichtigungen endgültig löschen?',
    ],

    'flash' => [
        'all_read' => 'Alle Benachrichtigungen wurden als gelesen markiert.',
        'deleted' => 'Benachrichtigung wurde gelöscht.',
        'read_deleted' => ':count gelesene Benachrichtigung(en) wurden gelöscht.',
        'rule_saved' => 'Benachrichtigungsregel für „:event" wurde gespeichert.',
    ],

    'mail' => [
        'subject' => ':event: :title',
        'subject_escalation' => 'Eskalation — :event: :title',
        'greeting' => 'Hallo :name,',
        'action' => 'Im System öffnen',
    ],

    'message' => [
        'issue_assigned' => ':actor hat dir diesen Offenen Punkt zugewiesen.',
        'customer_query_raised' => 'Ein Kunde hat eine Rückfrage gestellt.',
        'due_soon' => 'Fällig am :date.',
        'overdue' => 'Überfällig seit :date.',
        'followup_due_soon' => 'Folgeaktion fällig am :date.',
        'followup_overdue' => 'Folgeaktion überfällig seit :date.',
        'followup_fallback_title' => 'Folgeaktion zu Kommunikationsnotiz',
        'expiring_soon' => 'Dokument läuft am :date ab.',
        'expired' => 'Dokument ist seit :date abgelaufen.',
        'correction_requested_title' => 'Zeit-Korrekturantrag von :user für :date',
        'correction_decided_title' => 'Entscheidung zu deinem Korrekturantrag (:date)',
        'correction_approved' => 'Dein Antrag wurde genehmigt. :note',
        'correction_rejected' => 'Dein Antrag wurde abgelehnt. :note',
        'month_submitted_title' => 'Monatsabschluss :period von :user eingereicht',
        'certificate_expiring' => 'Zertifikat läuft am :date ab — Re-Zertifizierung rechtzeitig planen.',
        'corrective_action_overdue' => 'Korrekturmaßnahme überfällig seit :date.',
        'risk_review_due' => 'Review der akzeptierten Risikobewertung fällig am :date.',
        'vulnerability_overdue' => 'Schwachstelle überfällig seit :date.',
        'supplier_review_overdue' => 'Lieferanten-Review überfällig seit :date.',
        'sla_at_risk' => 'SLA-Lösungsfrist gefährdet — fällig am :date.',
        'sla_breached' => 'SLA-Lösungsfrist überschritten — fällig war :date.',
        'sla_quota_warning' => 'SLA-Kontingent zu :percent % ausgeschöpft (:consumed von :included Min.) im Zeitraum :period.',
        'asset_return_overdue' => 'Asset-Rückgabe überfällig — erwartet war :date.',
        'incident_critical' => 'Neuer kritischer Sicherheitsvorfall gemeldet.',
        'safety_critical_event' => 'Kritisches Sicherheitsereignis (:severity) am Ort :location gemeldet.',
        'qualification_expiring' => 'Qualifikation/Unterweisung läuft am :date ab.',
        'maintenance_due_soon' => 'Wartungsplan :label ist am :date fällig.',
        'maintenance_overdue' => 'Wartungsplan :label ist seit :date überfällig.',
    ],
];
