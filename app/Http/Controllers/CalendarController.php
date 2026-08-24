<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\User;
use App\Services\Calendar\CalendarEventService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CalendarController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(): View {
        return view('calendar.index');
    }

    public function events(Request $request, CalendarEventService $service): JsonResponse {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if($user === null, 401);

        // Guard statt Roh-Parse (Vollscan 2026-08-23, B10): Müll-Input fällt
        // auf den Monat zurück statt als 500 zu enden.
        [$start, $end] = $this->resolveNamedRangeWithDefault($request, 'start', 'end', static fn (): array => [
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        ]);

        $teamScope = $request->boolean('team') && $user->isAdmin();
        $rawFilterUser = (string) $request->query('user', '');
        $filterUserId = Sqid::decodeOrNumeric(User::class, $rawFilterUser);

        $events = $service->events($start, $end, $user, $teamScope, $filterUserId);

        return response()->json($events);
    }
}
