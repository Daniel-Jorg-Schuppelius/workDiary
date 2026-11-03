<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeShortfallWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\WorkQueue;

use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\Project\{Project, ProjectTimeShortfall};
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\CarbonFmt;
use Illuminate\Support\Facades\Gate;

/**
 * Offene Befunde unter Projekt-Soll im Arbeitsvorrat (Feature 194, MVP-1469):
 * wer alle Zeiten sieht, bekommt einen Vorgang je Projekt mit der Zahl der
 * Befunde; sonst nur die eigenen Tage. Erledigt sich mit Bestätigen oder
 * Nachbuchung.
 */
final class ProjectTimeShortfallWorkItemSource implements WorkItemSource {
    public function key(): string {
        return 'project_target';
    }

    public function label(): string {
        return (string) __('work-queue.source.project_target');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Reminder;
    }

    public function available(User $viewer): bool {
        return $viewer->isAdmin() || $viewer->can(Permission::ProjectViewAny->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $query = ProjectTimeShortfall::query()
            ->where('organization_id', $organization->id)
            ->open()
            ->with('project:id,name,slug,customer_id');
        $seesAll = $viewer->canViewAllTimeEntries();
        if (! $seesAll) {
            $query->where('user_id', $viewer->id);
        }
        $byProject = $query->orderBy('date')->get()->groupBy('project_id');
        foreach ($byProject as $group) {
            /** @var Project|null $project */
            $project = $group->first()?->project;
            if ($project === null || ! $gate->allows('view', $project)) {
                continue;
            }
            $oldest = $group->first();
            yield new WorkItem(
                source: $this->key(),
                key: $project->getMorphClass() . ':' . $project->id,
                title: $seesAll
                    ? (string) __(':count Personen-Tage unter Soll: :project', ['count' => $group->count(), 'project' => $project->name])
                    : (string) __('Unter Soll im Projekt :project', ['project' => $project->name]),
                url: route('projects.show', ['project' => $project, 'tab' => 'time', 'show' => 'shortfalls']),
                detail: $oldest !== null ? (string) __('seit :date', ['date' => CarbonFmt::fdate($oldest->date)]) : null,
                dueAt: null,
                personal: ! $seesAll,
                count: $group->count(),
            );
        }
    }
}
