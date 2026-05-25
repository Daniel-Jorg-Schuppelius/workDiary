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

use App\Services\Calendar\CalendarEventService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CalendarController extends Controller {
    public function index(): View {
        return view('calendar.index');
    }

    public function events(Request $request, CalendarEventService $service): JsonResponse {
        $user = Auth::user();
        abort_if($user === null, 401);

        $start = $request->query('start')
            ? CarbonImmutable::parse((string) $request->query('start'))
            : CarbonImmutable::now()->startOfMonth();
        $end = $request->query('end')
            ? CarbonImmutable::parse((string) $request->query('end'))
            : CarbonImmutable::now()->endOfMonth();

        $teamScope = $request->boolean('team') && $user->isAdmin();
        $filterUserId = $request->integer('user') ?: null;

        $events = $service->events($start, $end, $user, $teamScope, $filterUserId);

        return response()->json($events);
    }
}
