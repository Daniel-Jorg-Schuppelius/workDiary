<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportArchiveController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Concerns\{ListsLegacySupportOrders, RequiresLegacyAdmin};
use App\Legacy\Models\LegacySupportArchivedOrder;
use App\Legacy\Services\LegacySupportArchiveService;
use App\Legacy\Support\LegacyClock;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Archiv des Support-Portals (`kp_archiv`) und die Archivierung aus der Auftragsliste. */
class LegacySupportArchiveController extends Controller {
    use ListsLegacySupportOrders;
    use RequiresLegacyAdmin;

    private const int PER_PAGE = 50;

    public function index(Request $request, LegacySupportArchiveService $archive): View {
        $this->ensureAdmin();

        $query = LegacySupportArchivedOrder::query()->with('customer:id,kunde');
        $list = $this->applySupportOrderFilters($request, $query);
        [$sort, $dir] = $this->applySupportOrderSort($request, $query);

        // Wie im Altportal: archiviert wird bis zum Beginn des laufenden Monats.
        $cutoff = LegacyClock::now()->startOfMonth();

        return view('legacy.support.archive.index', [
            ...$list,
            'orders' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
            'customers' => $this->supportCustomersForSelect(),
            'cutoff' => $cutoff,
            'eligibleCount' => $archive->eligibleCount($cutoff),
            'writeEnabled' => (bool) config('app.legacy_write_enabled'),
        ]);
    }

    public function show(LegacySupportArchivedOrder $order): View {
        $this->ensureAdmin();
        $order->load('customer:id,kunde');

        return view('legacy.support.orders._show_dialog', [
            'order' => $order,
            'archived' => true,
            'writeEnabled' => false,
        ]);
    }

    public function run(Request $request, LegacySupportArchiveService $archive): RedirectResponse {
        $this->ensureAdmin();
        $data = $request->validate(['before' => ['required', 'date_format:Y-m-d']]);

        $moved = $archive->archiveBefore(Carbon::parse((string) $data['before'])->startOfDay());

        return redirect()->route('legacy.support.archive.index')
            ->with('success', trans_choice('legacy_support.flash.archived', $moved, ['count' => $moved]));
    }
}
