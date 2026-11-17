<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineCalendarSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Calendar;

use App\Models\Customer\CustomerDeadline;
use App\Models\Platform\{Organization, User};
use App\Services\Calendar\Contracts\CalendarEventSource;
use App\Services\Customer\Deadlines\CustomerDeadlineOverview;
use App\Services\Delegation\ActsForResolver;
use App\Support\OrganizationContext;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

/**
 * Offene Kundenfristen im Kalender (Feature 197, MVP-1633): im eigenen
 * Kalender die Fristen, für die man zuständig ist (auch in Vertretung), in der
 * Teamansicht alle bzw. die der gefilterten Person — nur mit Leserecht.
 */
final class CustomerDeadlineCalendarSource implements CalendarEventSource {
    private const string COLOR = '#7c3aed';

    public function __construct(
        private readonly CustomerDeadlineOverview $overview,
        private readonly ActsForResolver $actsFor,
    ) {}

    public function events(CarbonInterface $start, CarbonInterface $end, User $user, bool $teamScope, ?int $filterUserId): array {
        if (! Gate::forUser($user)->allows('viewAny', CustomerDeadline::class)) {
            return [];
        }
        $organization = OrganizationContext::current() ?? Organization::query()->find($user->organization_id);
        if (! $organization instanceof Organization) {
            return [];
        }

        $deadlines = CustomerDeadline::query()
            ->where('organization_id', $organization->id)
            ->open()
            ->whereBetween('due_on', DateRange::days($start, $end))
            ->when(! $teamScope, fn ($q) => $q->whereIn('responsible_user_id', $this->actsFor->assigneeIds($user)))
            ->when($teamScope && $filterUserId !== null, static fn ($q) => $q->where('responsible_user_id', $filterUserId))
            ->with('customer:id,name')
            ->orderBy('due_on')
            ->get();

        $events = [];
        foreach ($deadlines as $deadline) {
            $events[] = [
                'id' => 'customer-deadline-' . $deadline->id,
                'title' => $this->overview->kindLabel($organization, $deadline) . ' · ' . ($deadline->customer->name ?? ''),
                'start' => $deadline->due_on->toDateString(),
                'end' => null,
                'color' => self::COLOR,
                'url' => route('customer-deadlines.index', array_filter(['customer' => $deadline->customer?->sqid, 'status' => 'open'])),
            ];
        }

        return $events;
    }
}
