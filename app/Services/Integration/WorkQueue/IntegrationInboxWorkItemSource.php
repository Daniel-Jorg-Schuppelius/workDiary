<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationInboxWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\WorkQueue;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;

/**
 * Offene Zuordnungsfälle der Integrations-Inbox je Herkunft (Plugin, CSV-Import,
 * E-Mail-Eingang mit seinen Klärfällen) — ein geteilter Eingang, daher gebündelt.
 */
final class IntegrationInboxWorkItemSource implements WorkItemSource {
    private const ROUTE = 'admin.integration.inbox';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'integration_inbox';
    }

    public function label(): string {
        return (string) __('work-queue.source.integration_inbox');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Inbox;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE) && $viewer->canManageBilling();
    }

    public function items(User $viewer, Organization $organization): iterable {
        $perSource = IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('status', IntegrationInboxStatus::Open)
            ->selectRaw('plugin_id, COUNT(*) as open_count')
            ->groupBy('plugin_id')
            ->orderBy('plugin_id')
            ->toBase()
            ->get();

        foreach ($perSource as $row) {
            $pluginId = (string) $row->plugin_id;
            yield new WorkItem(
                source: $this->key(),
                key: 'integration_inbox:' . $pluginId,
                title: (string) __('work-queue.item.integration_inbox', ['source' => IntegrationInboxItem::sourceLabel($pluginId)]),
                url: route(self::ROUTE, ['plugin' => $pluginId]),
                count: (int) $row->open_count,
            );
        }
    }
}
