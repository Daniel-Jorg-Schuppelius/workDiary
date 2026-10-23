<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceChangeWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Article\WorkQueue;

use App\Enums\Article\PriceChangeRequestStatus;
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Article\PriceChangeRequest;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Illuminate\Support\Facades\Gate;

/** Beantragte Preisänderungen aus Margenregeln; der Antragsteller entscheidet nicht selbst. */
final class PriceChangeWorkItemSource implements WorkItemSource {
    private const ROUTE = 'pricing-margin-rules.approvals';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'price_change';
    }

    public function label(): string {
        return (string) __('work-queue.source.price_change');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE) && Gate::forUser($viewer)->allows(Permission::InventoryConfigure->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $requests = PriceChangeRequest::query()
            ->where('organization_id', $organization->id)
            ->where('status', PriceChangeRequestStatus::Requested)
            ->where('requested_by', '!=', $viewer->id)
            ->with('article:id,name')
            ->orderBy('created_at')
            ->get();

        foreach ($requests as $request) {
            yield new WorkItem(
                source: $this->key(),
                key: $request->getMorphClass() . ':' . $request->id,
                title: (string) __('work-queue.item.price_change', ['article' => $request->article->name ?? '—']),
                url: route(self::ROUTE),
            );
        }
    }
}
