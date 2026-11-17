<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\WorkQueue;

use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Customer\CustomerDeadline;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Services\Customer\Deadlines\CustomerDeadlineOverview;
use App\Services\Delegation\Contracts\DelegableWork;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * „Fristen bis Wochenende und überfällige“ (Feature 197, MVP-1633): offene
 * Kundenfristen der zuständigen Person, fällig bis zum kommenden Sonntag.
 * Zugleich delegierbare Arbeit (Feature 202, MVP-1648): die Vertretung sieht
 * die Fristen der vertretenen Person, gekennzeichnet „in Vertretung für …“.
 */
final class CustomerDeadlineWorkItemSource implements DelegableWork, WorkItemSource {
    public function __construct(private readonly CustomerDeadlineOverview $overview) {}

    public function key(): string {
        return 'customer_deadline';
    }

    public function label(): string {
        return (string) __('work-queue.source.customer_deadline');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Reminder;
    }

    public function available(User $viewer): bool {
        return Gate::forUser($viewer)->allows('viewAny', CustomerDeadline::class);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $until = CarbonImmutable::today()->endOfWeek(CarbonImmutable::SUNDAY);
        $deadlines = $this->assigned($viewer, (int) $organization->id)
            ->where('due_on', '<', DateRange::dayAfter($until))
            ->with('customer:id,name')
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();

        foreach ($deadlines as $deadline) {
            if (! $gate->allows('view', $deadline)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $deadline->getMorphClass() . ':' . $deadline->id,
                title: $this->overview->kindLabel($organization, $deadline) . ' · ' . $deadline->period_key->label(),
                url: route('customer-deadlines.index', ['customer' => $deadline->customer?->sqid, 'status' => 'open']),
                detail: $deadline->customer?->name,
                dueAt: CarbonImmutable::instance($deadline->due_on)->startOfDay(),
                personal: true,
            );
        }
    }

    /** Offene Fristen, für die die Person zuständig ist — nur die Anzahl (Vertretungsdialog). */
    public function count(User $user): int {
        return $this->assigned($user, (int) $user->organization_id)->count();
    }

    /** @return Builder<CustomerDeadline> */
    private function assigned(User $user, int $organizationId): Builder {
        return CustomerDeadline::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('responsible_user_id', $user->id)
            ->open();
    }
}
