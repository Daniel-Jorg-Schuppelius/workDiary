<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTimeWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\WorkQueue;

use App\Enums\Learning\LearningTimeApprovalStatus;
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Learning\LearningTimeSession;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Illuminate\Support\Facades\Gate;

/** Lernzeiten zur Anerkennung, gebündelt je Lernendem (eine Zeile statt einer je Sitzung). */
final class LearningTimeWorkItemSource implements WorkItemSource {
    private const ROUTE = 'learning.time-approvals.index';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'learning_time';
    }

    public function label(): string {
        return (string) __('work-queue.source.learning_time');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE) && Gate::forUser($viewer)->allows(Permission::LearningManage->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $perLearner = LearningTimeSession::query()
            ->where('organization_id', $organization->id)
            ->where('approval_status', LearningTimeApprovalStatus::Pending)
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $viewer->id)
            ->with('user:id,name')
            ->orderBy('started_at')
            ->get()
            ->groupBy('user_id');

        foreach ($perLearner as $userId => $sessions) {
            yield new WorkItem(
                source: $this->key(),
                key: 'learning_time:' . $userId,
                title: (string) __('work-queue.item.learning_time', ['name' => $sessions->first()->user->name ?? '—']),
                url: route(self::ROUTE),
                count: $sessions->count(),
            );
        }
    }
}
