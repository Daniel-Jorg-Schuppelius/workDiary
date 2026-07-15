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
        'rules_help_text' => 'For each event type the rule defines whether and via which channels notifications are sent and who receives them (affected person, roles, fixed people). Without a saved rule the shown default applies. Escalation: if an overdue event remains unresolved after the configured time, the escalation role is additionally notified. Additional escalation levels (2/3) notify further recipient groups, each after its own deadline. The “Calendar” channel adds date-related events to the organisation’s connected calendars (CalDAV/Microsoft 365/Google).',
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
        'escalation_ladder_help' => 'Optional additional level: if the event remains unresolved for the configured time after the previous escalation level was sent, this recipient group is additionally notified.',
        'escalation_level2' => 'Escalation level 2',
        'escalation_level3' => 'Escalation level 3',
        'escalation_level_after_hours' => 'After additional hours',
        'escalation_level_roles' => 'Level recipient roles',
        'escalation_level_users' => 'Level fixed recipients',
        'escalation_level_summary' => 'Level :level: +:hours h',
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
        'delete' => 'Delete',
        'delete_read' => 'Delete read',
        'open' => 'Open',
        'show_all' => 'Show all',
        'edit' => 'Edit',
        'save' => 'Save',
    ],

    'confirm' => [
        'delete_read' => 'Permanently delete all read notifications?',
    ],

    'flash' => [
        'all_read' => 'All notifications were marked as read.',
        'deleted' => 'Notification deleted.',
        'read_deleted' => ':count read notification(s) deleted.',
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
        'customer_query_raised' => 'A customer raised a query.',
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
        'certificate_expiring' => 'Certificate expires on :date — plan re-certification in time.',
        'corrective_action_overdue' => 'Corrective action overdue since :date.',
        'risk_review_due' => 'Review of the accepted risk assessment due on :date.',
        'vulnerability_overdue' => 'Vulnerability overdue since :date.',
        'supplier_review_overdue' => 'Supplier review overdue since :date.',
        'sla_at_risk' => 'SLA resolution deadline at risk — due on :date.',
        'sla_breached' => 'SLA resolution deadline breached — was due :date.',
        'sla_quota_warning' => 'SLA quota :percent% used (:consumed of :included min) in period :period.',
        'asset_return_overdue' => 'Asset return overdue — was expected :date.',
        'incident_critical' => 'New critical security incident reported.',
        'safety_critical_event' => 'Critical safety event (:severity) reported at :location.',
        'qualification_expiring' => 'Qualification/training expires on :date.',
        'maintenance_due_soon' => 'Maintenance plan :label is due on :date.',
        'maintenance_overdue' => 'Maintenance plan :label has been overdue since :date.',
    ],
];
