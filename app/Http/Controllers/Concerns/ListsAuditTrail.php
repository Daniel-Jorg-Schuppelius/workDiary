<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ListsAuditTrail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Audit\AuditLog;
use App\Models\Platform\{User, UserGroup};
use App\Support\{MorphMap, SortableQuery};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lesesicht auf einen Ausschnitt der Audit-Kette der eigenen Organisation,
 * eingegrenzt über Ereignis-Präfixe (Supportzugriffe MVP-004, Rollen- und
 * Rechteänderungen MVP-1302).
 */
trait ListsAuditTrail {
    /**
     * @param  list<string>  $prefixes
     */
    private function auditTrail(Request $request, array $prefixes, string $route, string $title, string $subtitle): View {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $filters = [
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'event' => (string) $request->query('event', ''),
            'actor' => (string) $request->query('actor', ''),
        ];

        [$sort, $dir] = SortableQuery::resolve($request, ['created_at', 'event'], 'created_at');

        $scope = function (Builder $query) use ($organization, $prefixes): void {
            $query->where('organization_id', $organization->id)
                ->where(function (Builder $q) use ($prefixes): void {
                    foreach ($prefixes as $prefix) {
                        $q->orWhereLikeEscaped('event', $prefix, 'prefix');
                    }
                });
        };

        $query = AuditLog::query()->where($scope);
        if (($from = $this->trailDate($filters['from'])) !== null) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if (($to = $this->trailDate($filters['to'])) !== null) {
            $query->where('created_at', '<=', $to->endOfDay());
        }
        if ($filters['event'] !== '' && array_any($prefixes, fn (string $p): bool => str_starts_with($filters['event'], $p))) {
            $query->where('event', $filters['event']);
        }
        if ($filters['actor'] !== '' && ctype_digit($filters['actor'])) {
            $query->where('user_id', (int) $filters['actor']);
        }

        $entries = $query->orderBy($sort, $dir)->paginate(50)->withQueryString();
        $items = collect($entries->items());

        $userIds = $items->pluck('user_id')
            ->merge($items->where('auditable_type', MorphMap::stableKey(User::class))->pluck('auditable_id'))
            ->filter()->unique();
        $groupIds = $items->where('auditable_type', MorphMap::stableKey(UserGroup::class))->pluck('auditable_id')->unique();

        return view('admin.audit-trail.index', [
            'organization' => $organization,
            'entries' => $entries,
            'users' => User::query()->whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id'),
            'groups' => UserGroup::query()->whereIn('id', $groupIds)->get(['id', 'name'])->keyBy('id'),
            'filters' => $filters,
            'eventOptions' => AuditLog::query()->where($scope)->distinct()->orderBy('event')->pluck('event')->all(),
            'sort' => $sort,
            'dir' => $dir,
            'route' => $route,
            'title' => $title,
            'subtitle' => $subtitle,
        ]);
    }

    private function trailDate(string $value): ?CarbonImmutable {
        if ($value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
