<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChangeInboxSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Approvals;

use App\Enums\ServiceTicket\ChangeStatus;
use App\Models\Approval\Approval;
use App\Models\Platform\User;
use App\Models\ServiceTicket\Change;
use App\Services\Approval\Contracts\ApprovalInboxSubject;
use App\Services\Approval\Dto\ApprovalInboxEntry;
use App\Services\ServiceTicket\ChangeService;
use Illuminate\Database\Eloquent\Model;

/** Changes (CAB-Freigabe) im Genehmigungs-Eingang (Feature 065, MVP-157). */
final class ChangeInboxSubject implements ApprovalInboxSubject {
    public function __construct(private readonly ChangeService $changes) {}

    public function approvableClass(): string {
        return Change::class;
    }

    public function eagerLoad(): array {
        return [];
    }

    public function awaitsDecision(Model $approvable): bool {
        return $approvable instanceof Change && $approvable->status === ChangeStatus::PendingApproval;
    }

    public function present(Model $approvable, User $viewer): ApprovalInboxEntry {
        if (! $approvable instanceof Change) {
            return new ApprovalInboxEntry('—');
        }

        return new ApprovalInboxEntry(
            $approvable->title,
            $viewer->can('view', $approvable) ? route('servicedesk.changes.show', $approvable) : null,
        );
    }

    public function decide(Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): void {
        $this->changes->decide($approval, $actor, $decision, $reason, $delegateUserId);
    }
}
