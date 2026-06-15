<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleReservationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Exceptions\VehicleReservationConflictException;
use App\Http\Requests\StoreVehicleReservationRequest;
use App\Models\{DiaryEntry, User, Vehicle, VehicleReservation};
use App\Services\Dispatch\VehicleReservationService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Fahrzeug-Reservierungen der Disposition (Feature 028). Reservierungen
 * können je Fahrzeug aufgelistet, am Auftrag angelegt und freigegeben
 * werden; Doppelreservierungen verhindert der Service.
 */
class VehicleReservationController extends Controller {
    public function __construct(private readonly VehicleReservationService $service) {}

    /** Reservierungsliste je Fahrzeug (oder org-weit). */
    public function index(Request $request): View {
        Gate::authorize('viewAny', VehicleReservation::class);

        $query = VehicleReservation::query()
            ->with(['vehicle', 'reservedBy', 'diaryEntry'])
            ->orderByDesc('reserved_from');

        $vehicle = null;
        if ($request->filled('vehicle')) {
            $vehicle = Vehicle::query()->where('sqid', $request->string('vehicle'))->first()
                ?? Vehicle::query()->find($request->integer('vehicle'));
            if ($vehicle !== null) {
                $query->where('vehicle_id', $vehicle->getKey());
            }
        }

        return view('vehicle-reservations.index', [
            'reservations' => $query->paginate(25)->withQueryString(),
            'vehicle' => $vehicle,
            'vehicles' => Vehicle::query()->whereNull('archived_at')->orderBy('label')->get(),
        ]);
    }

    public function store(StoreVehicleReservationRequest $request): RedirectResponse {
        Gate::authorize('create', VehicleReservation::class);

        $data = $request->validated();
        /** @var Vehicle $vehicle */
        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        Gate::authorize('view', $vehicle);

        /** @var DiaryEntry|null $diaryEntry */
        $diaryEntry = isset($data['diary_entry_id'])
            ? DiaryEntry::query()->find($data['diary_entry_id'])
            : null;

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $reservation = $this->service->reserve(
                $vehicle,
                $data['reserved_from'],
                $data['reserved_to'],
                $actor->id,
                $diaryEntry,
                $data['note'] ?? null,
            );
        } catch (VehicleReservationConflictException $e) {
            return back()->withInput()->withErrors([
                'reserved_from' => $e->getMessage(),
            ]);
        }

        return back()->with('success', __('Fahrzeug reserviert: :label.', [
            'label' => $reservation->vehicle?->displayName() ?? $vehicle->displayName(),
        ]));
    }

    public function destroy(VehicleReservation $vehicleReservation): RedirectResponse {
        Gate::authorize('delete', $vehicleReservation);

        $this->service->release($vehicleReservation);

        return back()->with('success', __('Reservierung aufgehoben.'));
    }
}
