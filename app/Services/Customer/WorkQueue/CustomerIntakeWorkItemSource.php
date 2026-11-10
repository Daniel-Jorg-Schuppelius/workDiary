<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\WorkQueue;

use App\Enums\Customer\IntakeStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Customer\CustomerIntake;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Kundenanfragen aus dem Portal (Feature 162), die bearbeitet werden dürfen
 * (CustomerIntakePolicy::update): neu eingegangene für alle Bearbeitenden,
 * angenommene nur für die zugewiesene Person.
 */
final class CustomerIntakeWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'customer-intakes.show';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'customer_intake';
    }

    public function label(): string {
        return (string) __('work-queue.source.customer_intake');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Inbox;
    }

    public function available(User $viewer): bool {
        $gate = Gate::forUser($viewer);

        return $this->features->routeEnabled(self::ROUTE)
            && $gate->allows('viewAny', CustomerIntake::class)
            && $gate->allows('update', CustomerIntake::class);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $open = CustomerIntake::query()
            ->where('organization_id', $organization->id)
            ->where(static fn ($query) => $query
                ->where('status', IntakeStatus::Submitted->value)
                ->orWhere(static fn ($mine) => $mine->where('status', IntakeStatus::InProgress->value)->where('assigned_user_id', $viewer->id)))
            ->where(static fn ($query) => $query->whereNull('submitted_by_user_id')->orWhere('submitted_by_user_id', '!=', $viewer->id))
            ->with('customer:id,name')
            ->orderBy('created_at')
            ->get();

        foreach ($open as $intake) {
            if (! $gate->allows('update', $intake)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $intake->getMorphClass() . ':' . $intake->id,
                title: (string) __('work-queue.item.customer_intake', ['number' => $intake->number, 'subject' => $intake->subject]),
                url: route(self::ROUTE, $intake),
                detail: $intake->customer->name ?? null,
                dueAt: $intake->desired_date !== null ? CarbonImmutable::instance($intake->desired_date) : null,
                personal: (int) $intake->assigned_user_id === (int) $viewer->id,
            );
        }
    }
}
