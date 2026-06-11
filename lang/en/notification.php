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
        'center' => 'Notifications',
        'center_subtitle' => 'Your in-app notifications — read and unread.',
        'empty' => 'No notifications.',
        'empty_message' => 'As soon as an event concerns you, it will appear here.',
        'rules' => 'Notification rules',
        'rules_subtitle' => 'Rules per event type: channels, recipients and escalation.',
        'rules_help' => 'How do notification rules work?',
        'rules_help_text' => 'For each event type the rule defines whether and via which channels notifications are sent and who receives them (affected person, roles, fixed people). Without a saved rule the shown default applies. Escalation: if an overdue event remains unresolved after the configured time, the escalation role is additionally notified.',
        'edit_rule' => 'Edit notification rule',
        'preferences' => 'Notifications',
    ],

    'field' => [
        'event' => 'Event',
        'enabled' => 'Active',
        'rule_enabled' => 'Notifications for this event enabled',
        'channels' => 'Channels',
        'recipients' => 'Recipients',
        'affected_user' => 'Affected person',
        'notify_affected_help' => 'Notify the affected person (e.g. assignee or requester)',
        'recipient_roles' => 'Recipient roles',
        'recipient_users' => 'Additional fixed recipients',
        'fixed_users' => 'fixed recipients',
        'escalation' => 'Escalation',
        'escalation_enabled' => 'Escalation enabled',
        'escalation_help' => 'If the event remains unresolved for the configured time after the initial notification, the escalation role is additionally notified.',
        'escalation_unsupported' => 'Escalation is only available for overdue events.',
        'escalate_after_hours' => 'Escalate after (hours)',
        'escalation_role' => 'Escalation role',
        'escalation_summary' => 'after :hours h to :role',
        'default_rule' => 'Default (not customised yet)',
        'unread' => 'new',
        'yes' => 'Yes',
        'no' => 'No',
        'mail_enabled' => 'Receive e-mail notifications',
        'quiet_from' => 'Quiet hours from',
        'quiet_to' => 'Quiet hours until',
        'preferences_help' => 'In-app notifications are always collected. E-mail (and push) can be disabled; during quiet hours no e-mails/push are sent.',
    ],

    'action' => [
        'mark_read' => 'Mark as read',
        'mark_all_read' => 'Mark all as read',
        'open' => 'Open',
        'show_all' => 'Show all',
        'edit' => 'Edit',
        'save' => 'Save',
    ],

    'flash' => [
        'all_read' => 'All notifications were marked as read.',
        'rule_saved' => 'Notification rule for ":event" was saved.',
    ],

    'mail' => [
        'subject' => ':event: :title',
        'subject_escalation' => 'Escalation — :event: :title',
        'greeting' => 'Hello :name,',
        'action' => 'Open in the system',
    ],

    'message' => [
        'issue_assigned' => ':actor assigned this open issue to you.',
        'due_soon' => 'Due on :date.',
        'overdue' => 'Overdue since :date.',
        'followup_due_soon' => 'Follow-up due on :date.',
        'followup_overdue' => 'Follow-up overdue since :date.',
        'followup_fallback_title' => 'Follow-up for communication note',
        'expiring_soon' => 'Document expires on :date.',
        'expired' => 'Document has been expired since :date.',
        'correction_requested_title' => 'Time correction request by :user for :date',
        'correction_decided_title' => 'Decision on your correction request (:date)',
        'correction_approved' => 'Your request was approved. :note',
        'correction_rejected' => 'Your request was rejected. :note',
        'month_submitted_title' => 'Month closure :period submitted by :user',
    ],
];
