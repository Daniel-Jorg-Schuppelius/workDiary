<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Approval\WorkQueue;

use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Approval\Approval;
use App\Models\Platform\{Organization, User};
use App\Services\Approval\ApprovalInbox;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;

/** Freigabestufen des Genehmigungs-Eingangs, für die der Nutzer zuständig ist. */
final class ApprovalWorkItemSource implements WorkItemSource {
    public function __construct(private readonly ApprovalInbox $inbox) {}

    public function key(): string {
        return 'approval';
    }

    public function label(): string {
        return (string) __('work-queue.source.approval');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return Gate::forUser($viewer)->allows(Permission::ServiceRequestApprove->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $steps = new EloquentCollection($this->inbox->openStepsFor($viewer)
            ->filter(static fn (Approval $a): bool => (int) $a->organization_id === (int) $organization->id)
            ->values()
            ->all());
        $this->inbox->preload($steps);

        foreach ($steps as $approval) {
            $entry = $this->inbox->entryFor($approval, $viewer);
            yield new WorkItem(
                source: $this->key(),
                key: $approval->approvable_type . ':' . $approval->approvable_id,
                title: $entry->title,
                url: route('approvals.index'),
                detail: implode(' · ', array_filter([$entry->type, $entry->reference])) ?: null,
                personal: (((array) $approval->approver_rule)['type'] ?? null) === 'user',
            );
        }
    }
}
