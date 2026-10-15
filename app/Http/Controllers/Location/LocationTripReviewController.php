<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationTripReviewController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Location;

use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Exceptions\LogbookViolationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\ConfirmLocationTripRequest;
use App\Models\Fleet\Vehicle;
use App\Models\Location\LocationPendingTrip;
use App\Services\Location\TripMaterializer;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prüf-Eingang für Fahrtvorschläge aus der Spur (MVP-1185): Fahrt zwischen
 * zwei Besuchen mit Fahrzeug und Zweck ins Fahrtenbuch übernehmen oder
 * verwerfen. Wie bei den Zeitvorschlägen sieht jede Person nur ihre eigenen.
 */
class LocationTripReviewController extends Controller {
    public function __construct(private readonly TripMaterializer $materializer) {}

    public function index(): View {
        $trips = LocationPendingTrip::query()
            ->where('user_id', $this->authUser()->id)
            ->open()
            ->orderBy('started_at')
            ->paginate(50);

        return view('location.trips', ['trips' => $trips, 'canBook' => $this->materializer->available()]);
    }

    public function confirmForm(LocationPendingTrip $trip): View {
        $this->authorizeOwnership($trip);
        $user = $this->authUser();

        return view('location._trip_confirm_dialog', [
            'trip' => $trip,
            'vehicles' => TravelLogVehicle::cases(),
            'fleetVehicles' => Vehicle::query()->active()->forUser((int) $user->id)->orderBy('label')->orderBy('license_plate')->get(),
            'tripKinds' => TripKind::cases(),
        ]);
    }

    public function confirm(ConfirmLocationTripRequest $request, LocationPendingTrip $trip): RedirectResponse {
        $this->authorizeOwnership($trip);

        try {
            $log = $this->materializer->confirm($trip, $this->authUser(), $request->validated());
        } catch (LogbookViolationException $e) {
            return back()->withInput()->withErrors($e->errors);
        }

        return redirect()->route('location.review.trips.index')
            ->with('success', __('Fahrt erfasst (:km km).', ['km' => NumberHelper::toGermanFormat((float) $log->distance_km, 2, withThousandsSeparator: true)]));
    }

    public function dismiss(LocationPendingTrip $trip): RedirectResponse {
        $this->authorizeOwnership($trip);
        $this->materializer->dismiss($trip, $this->authUser());

        return redirect()->route('location.review.trips.index')
            ->with('success', __('Vorschlag verworfen.'));
    }

    private function authorizeOwnership(LocationPendingTrip $trip): void {
        abort_unless($trip->user_id === $this->authUser()->id, Response::HTTP_FORBIDDEN);
    }
}
