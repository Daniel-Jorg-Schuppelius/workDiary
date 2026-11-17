<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMembershipWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club\WorkQueue;

use App\Enums\Club\ClubGroupMembershipStatus;
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Club\{ClubGroup, ClubGroupMembership};
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Aufnahmeanträge in Vereinsgruppen (MVP-842), die der Nutzer freigeben darf
 * (ClubGroupPolicy::decide): Vereinsverwaltung alle, Gruppenleitung die ihrer
 * Gruppen — dort persönlich. Den Antrag des eigenen Mitgliedskontos nie.
 */
final class ClubMembershipWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'club.groups.show';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'club_membership';
    }

    public function label(): string {
        return (string) __('work-queue.source.club_membership');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE)
            && ($viewer->isAdmin() || $viewer->can(Permission::ClubManage->value) || $viewer->can(Permission::ClubGroupLead->value));
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $requested = ClubGroupMembership::query()
            ->where('organization_id', $organization->id)
            ->where('status', ClubGroupMembershipStatus::Requested->value)
            ->whereHas('member', static fn ($query) => $query->where(static fn ($own) => $own->whereNull('user_id')->orWhere('user_id', '!=', $viewer->id)))
            ->with(['group', 'member'])
            ->orderBy('valid_from')
            ->get();

        foreach ($requested as $membership) {
            $group = $membership->group;
            if (! $group instanceof ClubGroup || ! $gate->allows('decide', $group)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $membership->getMorphClass() . ':' . $membership->id,
                title: (string) __('work-queue.item.club_membership', ['name' => $membership->member?->fullName() ?? '—', 'group' => $group->name]),
                url: route(self::ROUTE, $group),
                dueAt: CarbonImmutable::instance($membership->valid_from),
                personal: $group->isLedBy($viewer),
                // Liegezeit (MVP-1670): letzte Änderung am Gegenstand.
                movedAt: WorkItem::lastMoved($membership->updated_at),
            );
        }
    }
}
