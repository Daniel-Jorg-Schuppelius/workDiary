<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourDriverController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Diary\{Tour, TourStop};
use App\Models\Platform\User;
use App\Services\Routing\TourService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Fahreransicht der Tour (MVP-1183): nächster Stopp, Navigation, „angekommen“,
 * „erledigt“, „nicht angetroffen“, Tour starten und beenden — ohne Ortung,
 * offline über die Sync-Outbox.
 */
class TourDriverController extends Controller {
    public function __construct(private readonly TourService $tours) {}

    public function show(Tour $tour): View {
        Gate::authorize('view', $tour);
        $tour->load(['stops.diaryEntry.customer:id,name', 'vehicle:id,license_plate,label']);
        $next = $tour->stops->first(static fn(TourStop $stop): bool => $stop->departed_at === null);

        return view('tours.driver', ['tour' => $tour, 'next' => $next]);
    }

    public function arrive(Tour $tour, TourStop $stop): RedirectResponse {
        Gate::authorize('update', $tour);
        abort_unless((int) $stop->tour_id === (int) $tour->id, 404);
        /** @var User $auth */
        $auth = Auth::user();
        $this->tours->arriveAtStop($stop, $auth);

        return redirect()->route('tours.driver', $tour)->with('success', __('Ankunft erfasst.'));
    }

    public function depart(Request $request, Tour $tour, TourStop $stop): RedirectResponse {
        Gate::authorize('update', $tour);
        abort_unless((int) $stop->tour_id === (int) $tour->id, 404);
        $data = $request->validate(['outcome' => ['required', 'in:' . TourStop::OUTCOME_DONE . ',' . TourStop::OUTCOME_NOT_MET]]);
        /** @var User $auth */
        $auth = Auth::user();
        $this->tours->departStop($stop, $auth, (string) $data['outcome']);

        return redirect()->route('tours.driver', $tour)
            ->with('success', $data['outcome'] === TourStop::OUTCOME_DONE ? __('Stopp erledigt.') : __('Stopp als nicht angetroffen vermerkt.'));
    }
}
