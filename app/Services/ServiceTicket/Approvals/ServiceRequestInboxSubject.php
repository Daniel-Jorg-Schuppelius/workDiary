<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceRequestInboxSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Approvals;

use App\Enums\ServiceTicket\ServiceRequestStatus;
use App\Models\Approval\Approval;
use App\Models\Platform\User;
use App\Models\ServiceTicket\ServiceRequest;
use App\Services\Approval\Contracts\ApprovalInboxSubject;
use App\Services\Approval\Dto\ApprovalInboxEntry;
use App\Services\ServiceTicket\ServiceRequestService;
use Illuminate\Database\Eloquent\Model;

/** Service-Requests im Genehmigungs-Eingang (Feature 065, MVP-154). */
final class ServiceRequestInboxSubject implements ApprovalInboxSubject {
    public function __construct(private readonly ServiceRequestService $requests) {}

    public function approvableClass(): string {
        return ServiceRequest::class;
    }

    public function eagerLoad(): array {
        return ['ticket'];
    }

    public function awaitsDecision(Model $approvable): bool {
        return $approvable instanceof ServiceRequest && $approvable->status === ServiceRequestStatus::PendingApproval;
    }

    public function present(Model $approvable, User $viewer): ApprovalInboxEntry {
        $ticket = $approvable instanceof ServiceRequest ? $approvable->ticket : null;

        return $ticket === null
            ? new ApprovalInboxEntry('—')
            : new ApprovalInboxEntry($ticket->title, route('service-tickets.show', $ticket), $ticket->ticket_no);
    }

    public function decide(Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): void {
        $this->requests->decide($approval, $actor, $decision, $reason, $delegateUserId);
    }
}
