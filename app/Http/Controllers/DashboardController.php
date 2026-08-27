<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Dashboard\{DashboardLayoutService, DashboardService};
use App\Support\Dashboard\DashboardLayoutItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller {
    /**
     * Das Dashboard besteht ausschließlich aus Kacheln: welche erscheinen und
     * in welcher Reihenfolge, löst der DashboardLayoutService auf (Nutzerwahl
     * → Org-Vorgabe → Vorgabe der Kachel). Die Daten holt sich jede Kachel
     * selbst, damit ausgeblendete Kacheln keine Abfragen auslösen.
     *
     * Bereiche (Tabs) sind optional: ohne angelegte Bereiche rendert die
     * Ansicht eine einzige Fläche. Kacheln ohne Bereich — und solche, deren
     * Bereich gelöscht wurde — stehen über der Leiste und sind damit in jedem
     * Bereich sichtbar; so lag vor dem Kachel-Umbau die KPI-Zeile über den
     * Registerkarten.
     */
    public function __invoke(Request $request, DashboardLayoutService $layout, DashboardService $service): View {
        /** @var User $user */
        $user = $request->user();

        $tiles = $layout->visibleFor($user);
        $tabs = $layout->tabsFor($user);

        $grouped = null;
        $always = $tiles;
        if ($tabs !== []) {
            $known = array_column($tabs, 'key');
            $always = $tiles->reject(fn (DashboardLayoutItem $i) => in_array($i->tabKey, $known, true))->values();
            $grouped = $tiles
                ->filter(fn (DashboardLayoutItem $i) => in_array($i->tabKey, $known, true))
                ->groupBy(fn (DashboardLayoutItem $i): string => (string) $i->tabKey);
        }

        return view('dashboard.index', [
            'now' => $service->now(),
            'tiles' => $tiles,
            'tabs' => $tabs,
            'always' => $always,
            'grouped' => $grouped,
        ]);
    }
}
