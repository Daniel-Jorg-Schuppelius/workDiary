<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityFeedController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Timeline;

use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use App\Services\Timeline\ActivityFeedService;
use App\Support\{LookupCache, Sqid};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Organisationsweiter Aktivitäts-Feed (Feature 183): lesbare Sicht auf die
 * Audit-Kette — mit dem Recht des Audit-Protokolls, damit die Sicht keinen
 * Zugriff ausweitet. Feldänderungen zeigt weiter das Audit-Protokoll.
 */
class ActivityFeedController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    private const PER_PAGE = 50;

    public function __invoke(Request $request, ActivityFeedService $feed): View {
        Gate::authorize('viewAny', AuditLog::class);

        $typeKey = (string) $request->query('type', '');
        $type = AuditLog::FILTER_TYPES[$typeKey] ?? null;
        $actorId = Sqid::decode(User::class, (string) $request->query('user', ''));
        $page = max(1, (int) $request->query('page', 1));
        $range = $this->globalDateRange();

        $result = $feed->global(
            (int) $this->currentOrganization()->id,
            $range['from'],
            $range['to'],
            $type,
            $actorId,
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE,
        );

        return view('activity.index', [
            'items' => $result['items'],
            'hasMore' => $result['hasMore'],
            'page' => $page,
            'types' => AuditLog::FILTER_TYPES,
            'users' => LookupCache::userDropdown(),
            'filters' => ['type' => $type !== null ? $typeKey : '', 'user' => $actorId !== null ? (string) $request->query('user') : ''],
            'rangeLabel' => $range['label'],
        ]);
    }
}
