<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportsOverviewController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Navigation\NavigationRegistry;
use App\Services\Reporting\ReportsOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Auswertungs-Übersicht (reports.index): persönliche Kern-KPIs + zwei
 * Übersichts-Charts im globalen Zeitraum, darunter der gruppierte Einstieg
 * in alle Einzelauswertungen. Die Linkliste kommt aus der gefilterten
 * {@see NavigationRegistry} (Modul-/Rechte-/Per-User-Filter identisch zur
 * Sidebar — driftet nie).
 */
class ReportsOverviewController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly NavigationRegistry $registry,
        private readonly ReportsOverviewService $overview,
    ) {}

    public function index(Request $request): View {
        /** @var User $user */
        $user = Auth::user();
        [$from, $to] = $this->resolveRange($request);

        $sections = $this->registry->filterSidebar(
            $this->registry->sidebarBlueprint('duties.index'),
            $this->registry->hiddenNavKeys($user),
        );
        $reportsSection = collect($sections)->firstWhere('key', 'reports');
        $groups = array_values(array_filter(
            (array) ($reportsSection['groups'] ?? []),
            // Die Übersichtsgruppe verlinkt diese Seite selbst — hier ausblenden.
            fn(array $group): bool => ($group['key'] ?? '') !== 'reports-overview',
        ));

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'groups' => $groups,
            ...$this->overview->build($user, $from, $to),
        ]);
    }
}
