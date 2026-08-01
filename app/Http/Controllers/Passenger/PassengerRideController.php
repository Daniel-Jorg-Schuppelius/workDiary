<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerRideController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Passenger;

use App\Enums\Passenger\{RideOperationMode, RideOrderChannel, RidePriceKind, RideStatus};
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Passenger\{PassengerFareTariff, PassengerRide};
use App\Models\{User, Vehicle};
use App\Services\Passenger\PassengerRideService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Fahrtakten der Personenbeförderung (MVP-456): Annahme → Disposition →
 * Fahrt → Abschluss über die Pflichtgates des {@see PassengerRideService}.
 * Sichtbar nur mit installiertem Branchenprofil `taxi-mietwagen` (404 —
 * Muster RecipeMenuController).
 */
class PassengerRideController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(private readonly PassengerRideService $rides) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', PassengerRide::class);
        $this->passengerOrganization();
        [$from, $to] = $this->globalDateRangeBounds();

        $statusFilter = RideStatus::tryFrom($request->string('status')->toString())?->value;
        $modeFilter = RideOperationMode::tryFrom($request->string('mode')->toString())?->value;

        $rides = PassengerRide::query()
            ->with(['driver', 'vehicle', 'tariff'])
            // Offene Fahrten immer zeigen; abgeschlossene nur im globalen Zeitraum.
            ->where(function ($query) use ($from, $to): void {
                $query->open()->orWhereBetween('requested_at', [$from, $to]);
            })
            ->when($statusFilter !== null, fn($q) => $q->where('status', $statusFilter))
            ->when($modeFilter !== null, fn($q) => $q->where('operation_mode', $modeFilter))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('passenger.rides.index', [
            'rides' => $rides,
            'openCount' => PassengerRide::query()->open()->count(),
            'returnProofCount' => PassengerRide::query()
                ->where('operation_mode', RideOperationMode::RentalCar->value)
                ->where('status', RideStatus::Completed->value)
                ->whereNull('returned_to_base_at')
                ->whereNull('follow_up_ride_id')
                ->count(),
        ]);
    }

    public function show(PassengerRide $ride): View {
        Gate::authorize('view', $ride);
        $organization = $this->passengerOrganization();
        abort_unless($ride->organization_id === $organization->id, 404);

        $ride->load(['diaryEntry', 'driver', 'vehicle', 'concession', 'tariff', 'shiftSettlement', 'followUpRide']);

        return view('passenger.rides.show', [
            'ride' => $ride,
            'drivers' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::query()->orderBy('license_plate')->get(),
            'tariffs' => PassengerFareTariff::query()->validFor($ride->operation_mode)->orderBy('name')->get(),
            'followUpCandidates' => PassengerRide::query()->open()
                ->where('id', '!=', $ride->id)
                ->where('operation_mode', RideOperationMode::RentalCar->value)
                ->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    /** Dialogfragment „Fahrt annehmen" (data-entry-modal-trigger). */
    public function create(): View {
        Gate::authorize('create', PassengerRide::class);
        $this->passengerOrganization();

        return view('passenger.rides._form_dialog', [
            'customers' => \App\Models\Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', PassengerRide::class);
        $organization = $this->passengerOrganization();

        $request->merge(['customer_id' => Sqid::decodeOrNumeric(\App\Models\Customer::class, $request->input('customer_id'))]);
        $validated = $request->validate([
            'operation_mode' => ['required', 'string', 'in:' . implode(',', RideOperationMode::values())],
            'order_channel' => ['required', 'string', 'in:' . implode(',', RideOrderChannel::values())],
            'customer_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'pickup_address' => ['required', 'string', 'max:500'],
            'destination_address' => ['nullable', 'string', 'max:500'],
            'destination_open' => ['nullable', 'boolean'],
            'window_start' => ['nullable', 'date'],
            'window_end' => ['nullable', 'date', 'after_or_equal:window_start'],
            'passenger_count' => ['nullable', 'integer', 'min:1', 'max:60'],
            'luggage_count' => ['nullable', 'integer', 'min:0', 'max:60'],
            'child_seats' => ['nullable', 'integer', 'min:0', 'max:10'],
            'wheelchair' => ['nullable', 'boolean'],
            'animal' => ['nullable', 'boolean'],
            'barrier_free_required' => ['nullable', 'boolean'],
            'passenger_name' => ['nullable', 'string', 'max:200'],
            'passenger_contact' => ['nullable', 'string', 'max:200'],
            'order_receipt_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $ride = $this->rides->accept($organization, $request->user() ?? abort(401), $validated);

        return redirect()->route('passenger-rides.show', $ride)->with('status', (string) __('passenger.flash.accepted'));
    }

    /** Disposition: Fahrer + Fahrzeug mit Pflichtgates zuweisen. */
    public function assign(Request $request, PassengerRide $ride): RedirectResponse {
        Gate::authorize('update', $ride);
        $this->assertRideInOrganization($ride);

        $request->merge([
            'driver_user_id' => Sqid::decodeOrNumeric(User::class, $request->input('driver_user_id')),
            'vehicle_id' => Sqid::decodeOrNumeric(Vehicle::class, $request->input('vehicle_id')),
        ]);
        $validated = $request->validate([
            'driver_user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'vehicle_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('vehicles')],
        ]);

        $driver = User::inCurrentOrganization()->findOrFail((int) $validated['driver_user_id']);
        $vehicle = Vehicle::query()->findOrFail((int) $validated['vehicle_id']);
        $this->rides->assign($ride, $driver, $vehicle, $request->user() ?? abort(401));

        return redirect()->route('passenger-rides.show', $ride)->with('status', (string) __('passenger.flash.assigned'));
    }

    /** Fahrtbeginn: Tarif-/Festpreis wird eingefroren. */
    public function start(Request $request, PassengerRide $ride): RedirectResponse {
        Gate::authorize('update', $ride);
        $this->assertRideInOrganization($ride);

        $request->merge(['tariff_id' => Sqid::decodeOrNumeric(PassengerFareTariff::class, $request->input('tariff_id'))]);
        $validated = $request->validate([
            'price_kind' => ['required', 'string', 'in:' . implode(',', RidePriceKind::values())],
            'tariff_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('passenger_fare_tariffs')],
            'planned_net' => ['nullable', 'numeric', 'min:0'],
            'estimated_km' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $tariff = isset($validated['tariff_id'])
            ? PassengerFareTariff::query()->findOrFail((int) $validated['tariff_id'])
            : null;
        $this->rides->start($ride, [
            'price_kind' => (string) $validated['price_kind'],
            'tariff' => $tariff,
            'planned_net' => $validated['planned_net'] ?? null,
            'estimated_km' => $validated['estimated_km'] ?? null,
            'estimated_minutes' => isset($validated['estimated_minutes']) ? (int) $validated['estimated_minutes'] : null,
        ], $request->user() ?? abort(401));

        return redirect()->route('passenger-rides.show', $ride)->with('status', (string) __('passenger.flash.started'));
    }

    /** Statuswechsel wartend/besetzt entlang der erlaubten Pfade. */
    public function transition(Request $request, PassengerRide $ride): RedirectResponse {
        Gate::authorize('update', $ride);
        $this->assertRideInOrganization($ride);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . RideStatus::Waiting->value . ',' . RideStatus::Occupied->value],
        ]);
        $this->rides->transition($ride, RideStatus::from((string) $validated['status']), $request->user() ?? abort(401));

        return redirect()->route('passenger-rides.show', $ride);
    }

    /** Fahrtabschluss: Gerätewert, Steuerentscheidung, Zahlung sind Pflicht. */
    public function complete(Request $request, PassengerRide $ride): RedirectResponse {
        Gate::authorize('update', $ride);
        $this->assertRideInOrganization($ride);

        $validated = $request->validate([
            'meter_net' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payment_method' => ['required', 'string', 'max:24'],
            'occupied_km' => ['nullable', 'numeric', 'min:0'],
            'empty_km' => ['nullable', 'numeric', 'min:0'],
            'waiting_seconds' => ['nullable', 'integer', 'min:0'],
            'odometer_end_km' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->rides->complete($ride, $validated, $request->user() ?? abort(401));

        return redirect()->route('passenger-rides.show', $ride)->with('status', (string) __('passenger.flash.completed'));
    }

    /** Storno / No-show / Abbruch mit Begründung. */
    public function close(Request $request, PassengerRide $ride): RedirectResponse {
        Gate::authorize('update', $ride);
        $this->assertRideInOrganization($ride);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', [RideStatus::Cancelled->value, RideStatus::NoShow->value, RideStatus::Aborted->value])],
            'reason' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->rides->close($ride, RideStatus::from((string) $validated['status']), (string) $validated['reason'], $request->user() ?? abort(401), $validated['note'] ?? null);

        return redirect()->route('passenger-rides.show', $ride)->with('status', (string) __('passenger.flash.closed'));
    }

    /** Mietwagen: Rückkehr zum Betriebssitz oder Folgeauftrag (§ 49 IV PBefG). */
    public function recordReturn(Request $request, PassengerRide $ride): RedirectResponse {
        Gate::authorize('update', $ride);
        $this->assertRideInOrganization($ride);

        $request->merge(['follow_up_ride_id' => Sqid::decodeOrNumeric(PassengerRide::class, $request->input('follow_up_ride_id'))]);
        $validated = $request->validate([
            'follow_up_ride_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('passenger_rides')],
        ]);
        $followUp = isset($validated['follow_up_ride_id'])
            ? PassengerRide::query()->findOrFail((int) $validated['follow_up_ride_id'])
            : null;
        $this->rides->recordReturn($ride, $request->user() ?? abort(401), $followUp);

        return redirect()->route('passenger-rides.show', $ride)->with('status', (string) __('passenger.flash.return_recorded'));
    }

    private function assertRideInOrganization(PassengerRide $ride): void {
        $organization = $this->passengerOrganization();
        abort_unless($ride->organization_id === $organization->id, 404);
    }

    /** Branchenprofil-Gate: 404 ohne installiertes Profil (Muster Recipes). */
    private function passengerOrganization(): Organization {
        $organization = $this->currentOrganization();
        abort_unless($this->rides->isPassengerProfileActive($organization), 404);

        return $organization;
    }
}
