<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CapacityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Platform\Team;
use App\Services\Hr\CapacityPlanningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Personal-Kapazität je Team und Woche (MVP-940). */
class CapacityController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(Request $request, CapacityPlanningService $capacity): View {
        Gate::authorize('viewAny', Team::class);
        $weeks = max(2, min(12, $request->integer('weeks', CapacityPlanningService::DEFAULT_WEEKS)));

        $organization = $this->currentOrganization();

        return view('teams.capacity', ['rows' => $capacity->plan($organization, $weeks), 'weeks' => $weeks, 'openHeadcount' => $capacity->openHeadcount($organization)]);
    }
}
