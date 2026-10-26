<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;

/**
 * Prüffälle der Serienvorlagen im Arbeitsvorrat (Feature 185, MVP-1412): je
 * Art ein Eintrag mit Zähler, verlinkt auf die Lizenzkonten. Nur für Nutzer,
 * die das Register pflegen dürfen.
 */
final class PlanWorkItemSource implements WorkItemSource {
    private const ROUTE = 'finance.resale.accounts.index';

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly PlanAudit $audit,
        private readonly RecurringPlans $plans,
    ) {}

    public function key(): string {
        return 'resale_plan';
    }

    public function label(): string {
        return (string) __('work-queue.source.resale_plan');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Inbox;
    }

    public function available(User $viewer): bool {
        return $viewer->organization !== null && $this->plans->availableFor($viewer->organization) && $this->features->routeEnabled(self::ROUTE) && $viewer->can(Permission::ResellingManage->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        foreach ($this->audit->countByKind($organization) as $kind => $count) {
            yield new WorkItem(
                source: $this->key(),
                key: 'resale_plan:' . $kind,
                title: (string) __('resale.plan.finding.' . $kind . '_short'),
                url: route(self::ROUTE),
                count: $count,
            );
        }
    }
}
