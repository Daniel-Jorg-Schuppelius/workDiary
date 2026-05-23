<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueEventType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\OpenIssue;

enum OpenIssueEventType: string {
    case Created = 'issue.created';
    case Assigned = 'issue.assigned';
    case Started = 'issue.started';
    case Blocked = 'issue.blocked';
    case Unblocked = 'issue.unblocked';
    case Completed = 'issue.completed';
    case WontDo = 'issue.wontDo';
    case Reopened = 'issue.reopened';
    case DueDateChanged = 'issue.dueDateChanged';
    case SeverityChanged = 'issue.severityChanged';
    case VisibilityChanged = 'issue.visibilityChanged';
    case CommentAdded = 'issue.commentAdded';
    case AttachmentAdded = 'issue.attachmentAdded';
}
