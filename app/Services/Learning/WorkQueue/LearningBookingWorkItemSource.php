<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningBookingWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\WorkQueue;

use App\Enums\Learning\LearningBookingStatus;
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Learning\LearningBooking;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Illuminate\Support\Facades\Gate;

/** Kursbuchungen, die auf Zusage oder Absage warten (Feature 149); die eigene Buchung entscheidet niemand selbst. */
final class LearningBookingWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'learning.bookings.index';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'learning_booking';
    }

    public function label(): string {
        return (string) __('work-queue.source.learning_booking');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE) && Gate::forUser($viewer)->allows(Permission::LearningManage->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $requested = LearningBooking::query()
            ->where('organization_id', $organization->id)
            ->where('status', LearningBookingStatus::Requested->value)
            ->where(static fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $viewer->id))
            ->with(['course:id,title', 'user:id,name', 'externalParticipant', 'customer:id,name'])
            ->orderBy('requested_at')
            ->get();

        foreach ($requested as $booking) {
            yield new WorkItem(
                source: $this->key(),
                key: $booking->getMorphClass() . ':' . $booking->id,
                title: (string) __('work-queue.item.learning_booking', ['name' => $booking->bookerName() !== '' ? $booking->bookerName() : '—']),
                url: route(self::ROUTE),
                detail: $booking->course?->title,
                // Liegezeit (MVP-1670): letzte Änderung am Gegenstand.
                movedAt: WorkItem::lastMoved($booking->updated_at),
            );
        }
    }
}
