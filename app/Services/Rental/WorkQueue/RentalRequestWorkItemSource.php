<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRequestWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Rental\WorkQueue;

use App\Enums\Rental\RentalRequestStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\Rental\{RentalCase, RentalRequest};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Portal-Verleihanfragen, die auf Annahme oder Ablehnung warten (Feature 073);
 * entscheiden darf, wer Verleihakten anlegt (RentalRequestController).
 * Anfragen mit ausstehender Vorauszahlung warten auf den Kunden.
 */
final class RentalRequestWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'rental.requests.index';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'rental_request';
    }

    public function label(): string {
        return (string) __('work-queue.source.rental_request');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Inbox;
    }

    public function available(User $viewer): bool {
        $gate = Gate::forUser($viewer);

        return $this->features->routeEnabled(self::ROUTE)
            && $gate->allows('viewAny', RentalCase::class)
            && $gate->allows('create', RentalCase::class);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $requests = RentalRequest::query()
            ->where('organization_id', $organization->id)
            ->where('status', RentalRequestStatus::Requested->value)
            ->where(static fn ($query) => $query->whereNull('portal_user_id')->orWhere('portal_user_id', '!=', $viewer->id))
            ->with('customer:id,name')
            ->orderBy('starts_at')
            ->get();

        foreach ($requests as $request) {
            yield new WorkItem(
                source: $this->key(),
                key: $request->getMorphClass() . ':' . $request->id,
                title: (string) __('work-queue.item.rental_request', ['customer' => $request->customer->name ?? '—']),
                url: route(self::ROUTE),
                detail: CarbonFmt::fdatetime($request->starts_at) . ' – ' . CarbonFmt::fdatetime($request->ends_at),
                dueAt: CarbonImmutable::instance($request->starts_at),
            );
        }
    }
}
