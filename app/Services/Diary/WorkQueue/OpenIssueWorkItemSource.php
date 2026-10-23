<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\WorkQueue;

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Diary\OpenIssue;
use App\Models\Platform\{Organization, User};
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\EntityUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** Offene Punkte, die dem Nutzer zugewiesen sind; verlinkt auf ihren Gegenstand. */
final class OpenIssueWorkItemSource implements WorkItemSource {
    public function key(): string {
        return 'open_issue';
    }

    public function label(): string {
        return (string) __('work-queue.source.open_issue');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return true;
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $issues = OpenIssue::query()
            ->where('organization_id', $organization->id)
            ->where('assignee_user_id', $viewer->id)
            ->whereIn('status', OpenIssueStatus::openValues())
            ->with('subject')
            ->orderBy('due_at')
            ->get();

        foreach ($issues as $issue) {
            if (! $gate->allows('view', $issue)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $issue->getMorphClass() . ':' . $issue->id,
                title: $issue->title,
                // Zugewiesene Punkte können an fremden Aufträgen hängen — Link nur, wenn er sich öffnen lässt.
                url: $issue->subject !== null && $gate->allows('view', $issue->subject) ? EntityUrl::for($issue->subject) : null,
                detail: $issue->severity->label(),
                dueAt: $issue->due_at !== null ? CarbonImmutable::instance($issue->due_at) : null,
                personal: true,
            );
        }
    }
}
