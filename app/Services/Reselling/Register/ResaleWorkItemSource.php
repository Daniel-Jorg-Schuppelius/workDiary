<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;

/**
 * Aufgaben des Register-Überblicks im Arbeitsvorrat (Phase 175): dieselben
 * Zähler, dieselben Ziele. Befunde der Serienvorlagen meldet
 * {@see \App\Services\Reselling\Plan\PlanWorkItemSource}; Verlängerungen sind Termine, keine Arbeit.
 */
final class ResaleWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'finance.resale.index';

    private const array ELSEWHERE = ['plan_findings', 'renewals'];

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly ResaleOverview $overview,
    ) {}

    public function key(): string {
        return 'resale';
    }

    public function label(): string {
        return (string) __('work-queue.source.resale');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Inbox;
    }

    public function available(User $viewer): bool {
        return $viewer->organization !== null && $this->features->routeEnabled(self::ROUTE) && $viewer->can(Permission::ResellingView->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $manages = $viewer->can(Permission::ResellingManage->value);
        foreach ($this->overview->build($organization)['tasks'] as $task) {
            if (in_array($task['key'], self::ELSEWHERE, true) || ($task['manage'] && ! $manages)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: 'resale:' . $task['key'],
                title: $task['title'],
                // Dialog-Aufgaben öffnen sich nur aus dem Überblick heraus.
                url: $task['dialog'] ? route(self::ROUTE) : $task['url'],
                detail: $task['detail'],
                count: $task['count'],
            );
        }
    }
}
