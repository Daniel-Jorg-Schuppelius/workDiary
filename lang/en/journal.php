<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : journal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Journal-Ereignisse (MVP-864): `journal.<modulcode>.<event>`, gelesen von
// JournalEntry::label(); Ereignisse mit Punkten liegen verschachtelt
// (`order.created` → ['order' => ['created' => …]]).
return [
    'finance' => [
        'analyzed' => 'Analysed',
        'blocked' => 'Blocked',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        'cutover_executed' => 'Cut-over executed',
        'item_decided' => 'Item decided',
        'parallel_run_started' => 'Parallel run started',
        'planned' => 'Planned',
        'status_changed' => 'Status changed',
        'position_edited' => 'Position edited',
        'position_removed' => 'Position removed',
        'positions_merged' => 'Positions merged',
        'texts_edited' => 'Texts edited',
        'discarded' => 'Discarded',
        'finalized' => 'Finalised',
        'sources_removed' => 'Sources removed',
        'return_processed' => 'Return processed',
        'skonto_accepted' => 'Cash discount accepted',
        'unmatched' => 'Allocation removed',
        'accounting' => [
            'opening_balance_imported' => 'Opening balance imported',
        ],
    ],
    'privacy' => [
        'assessed' => 'Assessed',
        'authority_report_recorded' => 'Authority report recorded',
        'closed' => 'Closed',
        'controller_notified' => 'Controller notified',
        'measure_added' => 'Measure added',
        'measure_completed' => 'Measure completed',
        'notification_decided' => 'Notification decided',
        'opened' => 'Opened',
        'reported' => 'Reported',
        'deadline_reminder' => 'Deadline reminder',
        'assigned' => 'Assigned',
        'decided' => 'Decided',
        'identity_verified' => 'Identity verified',
        'portal_email_confirmed' => 'Portal e-mail confirmed',
        'portal_receipt_failed' => 'Portal receipt failed',
        'portal_receipt_sent' => 'Portal receipt sent',
        'portal_submitted' => 'Submitted via portal',
        'shredded' => 'Shredded',
        'subject_export_generated' => 'Data subject export generated',
    ],
    'whistleblowing' => [
        'assigned' => 'Assigned',
        'deadline_reminder' => 'Deadline reminder',
        'decided' => 'Decided',
        'identity_verified' => 'Identity verified',
        'opened' => 'Opened',
        'portal_email_confirmed' => 'Portal e-mail confirmed',
        'portal_receipt_failed' => 'Portal receipt failed',
        'portal_receipt_sent' => 'Portal receipt sent',
        'portal_submitted' => 'Submitted via portal',
        'shredded' => 'Shredded',
        'subject_export_generated' => 'Export generated',
    ],
    'agile' => [
        'backlog' => [
            'added' => 'Added to backlog',
            'removed' => 'Removed from backlog',
            'reranked' => 'Backlog reordered',
        ],
        'column' => [
            'moved' => 'Column changed',
        ],
        'sprint' => [
            'item_added' => 'Added to sprint',
            'item_removed' => 'Removed from sprint',
            'started' => 'Sprint started',
            'completed' => 'Sprint completed',
            'cancelled' => 'Sprint cancelled',
        ],
        'item' => [
            'blocked' => 'Blocked',
            'unblocked' => 'Unblocked',
        ],
        'points' => [
            'changed' => 'Story points changed',
        ],
        'override' => [
            'wip' => 'WIP limit overridden',
            'dod' => 'Definition of done overridden',
            'criteria' => 'Acceptance criteria overridden',
        ],
        'epic' => [
            'assigned' => 'Epic assigned',
        ],
    ],
    'diary' => [
        'order' => [
            'created' => 'Order created',
            'accept' => 'Order accepted',
            'start' => 'Order started',
            'pause' => 'Order paused',
            'resume' => 'Order resumed',
            'complete' => 'Order completed',
            'acceptance' => 'Acceptance started',
            'invoice' => 'Invoice triggered',
            'cancel' => 'Order cancelled',
        ],
        'dispatch' => [
            'gap_fill_applied' => 'Gap-fill suggestion applied',
            'gap_fill_dismissed' => 'Gap-fill suggestion dismissed',
            'calendly_confirmed' => 'Calendly request confirmed',
        ],
        'issue' => [
            'created' => 'Open issue created',
            'assigned' => 'Assigned',
            'started' => 'Started',
            'blocked' => 'Blocked',
            'unblocked' => 'Unblocked',
            'completed' => 'Completed',
            'wontDo' => 'Won\'t do',
            'reopened' => 'Reopened',
            'dueDateChanged' => 'Due date changed',
            'severityChanged' => 'Severity changed',
            'visibilityChanged' => 'Visibility changed',
            'commentAdded' => 'Comment added',
            'attachmentAdded' => 'Attachment added',
        ],
    ],
    'time' => [
        'month' => [
            'approved' => 'Month approved',
            'locked' => 'Month locked',
            'rejected' => 'Month rejected',
            'submitted' => 'Month submitted',
        ],
        'export' => [
            'delivered' => 'Delivered',
            'downloaded' => 'Downloaded',
            'line_updated' => 'Line updated',
            'preparing' => 'Preparing',
            'ready' => 'Ready',
            'rejected' => 'Rejected',
            'superseded' => 'Superseded',
            'delivered_auto' => 'Delivered automatically',
            'delivery_failed' => 'Delivery failed',
        ],
    ],
    'procedure' => [
        'procedure' => [
            'runStarted' => 'Run started',
            'stepCompleted' => 'Step completed',
            'stepFailed' => 'Step failed',
            'stepDeviated' => 'Step deviated',
            'stepNA' => 'Step not applicable',
            'stepUnlocked' => 'Step unlocked',
            'stepBlocked' => 'Step blocked',
            'runCompleted' => 'Run completed',
            'runCompletionRejected' => 'Completion rejected',
            'runAborted' => 'Run aborted',
            'secondPersonAssigned' => 'Second person assigned',
            'secondPersonSigned' => 'Second person signed',
            'secondPersonRequested' => 'Second person requested',
            'secondPersonRevoked' => 'Second person revoked',
            'backupRegistered' => 'Backup registered',
            'backupVerified' => 'Backup verified',
            'backupRejected' => 'Backup rejected',
            'deviationRecorded' => 'Deviation recorded',
            'deviationUpdated' => 'Deviation updated',
            'deviationActionTriggered' => 'Deviation action triggered',
            'criticalRiskAccepted' => 'Critical risk accepted',
        ],
    ],
    'protocol' => [
        'protocol' => [
            'created' => 'Report created',
            'itemAdded' => 'Item added',
            'itemRemoved' => 'Item removed',
            'itemReordered' => 'Items reordered',
            'itemFilled' => 'Item filled in',
            'requestedReview' => 'Review requested',
            'returnedToDraft' => 'Returned to draft',
            'signed' => 'Signed',
            'archived' => 'Archived',
            'supersededBy' => 'Superseded by a newer version',
            'attachmentAdded' => 'Attachment added',
            'attachmentRemoved' => 'Attachment removed',
            'signatureRequested' => 'Signature requested',
            'signatureLinkOpened' => 'Signature link opened',
            'signatureRejected' => 'Signature rejected',
            'signatureLinkRevoked' => 'Signature link revoked',
            'customerQueryRaised' => 'Customer query raised',
            'customerQueryAnswered' => 'Customer query answered',
            'pdfRendered' => 'PDF rendered',
            'pdfDownloaded' => 'PDF downloaded',
            'item' => [
                'photoAdded' => 'Photo added',
                'photoRemoved' => 'Photo removed',
                'photoReordered' => 'Photos reordered',
                'photoUpdatedCaption' => 'Caption changed',
            ],
        ],
    ],
    'learning' => [
        'status_changed' => 'Status changed',
    ],
    'auth' => [
        'auth' => [
            'lockout' => 'Account locked',
            '2fa_failed' => 'Second factor failed',
            'password_reset_requested' => 'Password reset requested',
            'impossible_travel' => 'Impossible travel detected',
        ],
        'wb' => [
            'login_failed' => 'Reporting channel: login failed',
        ],
        'api' => [
            'token_invalid' => 'API token invalid',
        ],
        'webhook' => [
            'signature_invalid' => 'Webhook signature invalid',
        ],
        'sso' => [
            'failed' => 'SSO failed',
        ],
        'terminal' => [
            'badge_unknown' => 'Unknown terminal badge',
        ],
        'admin' => [
            'ip_blocked' => 'Platform admin IP blocked',
        ],
    ],
];
