<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnergyLogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\{ResolvesGlobalDateRange, ResolvesRequestedUser};
use App\Http\Requests\SaveEnergyLogRequest;
use App\Models\{EnergyLog, User, Vehicle};
use App\Services\Fleet\EnergyLogService;
use App\Support\{SortableQuery, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnergyLogController extends Controller {
    use ResolvesGlobalDateRange, ResolvesRequestedUser;

    public function __construct(private readonly EnergyLogService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', EnergyLog::class);

        /** @var User $auth */
        $auth = Auth::user();
        $target = $this->resolveTargetUser($request, $auth);
        [$from, $to] = $this->resolveRange($request);

        $query = EnergyLog::query()
            ->with(['vehicle:id,license_plate,label', 'user:id,name'])
            ->whereBetween('started_at', [$from, $to]);

        if ($target !== null) {
            $query->where('user_id', $target->id);
        }

        $rawVehicle = (string) $request->query('vehicle', '');
        $vehicleId = Sqid::decodeOrNumeric(Vehicle::class, $rawVehicle);
        $selectedVehicleSqid = $vehicleId !== null ? Sqid::encode(Vehicle::class, $vehicleId) : null;
        if ($vehicleId !== null) {
            $query->where('vehicle_id', $vehicleId);
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'started_at' => 'started_at',
            'type' => 'energy_type',
            'quantity' => 'quantity',
            'cost' => 'cost_total',
            'odometer' => 'odometer_km',
            'distance' => 'distance_since_last',
        ], 'started_at', 'desc');

        $logs = $query->paginate(25)->withQueryString();

        $totalsBase = EnergyLog::query()
            ->whereBetween('started_at', [$from, $to]);
        if ($target !== null) {
            $totalsBase->where('user_id', $target->id);
        }
        if ($vehicleId !== null) {
            $totalsBase->where('vehicle_id', $vehicleId);
        }

        $totals = [
            'cost' => (float) (clone $totalsBase)->sum('cost_total'),
            'liters' => (float) (clone $totalsBase)->where('unit', EnergyLog::UNIT_LITER)->sum('quantity'),
            'kwh' => (float) (clone $totalsBase)->where('unit', EnergyLog::UNIT_KWH)->sum('quantity'),
            'distance' => (int) (clone $totalsBase)->sum('distance_since_last'),
        ];

        return view('energy-logs.index', [
            'logs' => $logs,
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
            'vehicles' => $this->vehiclesForUser($auth),
            'selectedVehicleSqid' => $selectedVehicleSqid,
            'targetUser' => $target,
            'selectableUsers' => $auth->isAdmin() ? $this->loadSelectableUsers() : null,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', EnergyLog::class);

        /** @var User $auth */
        $auth = Auth::user();

        return view('energy-logs._form_dialog', [
            'log' => null,
            'vehicles' => $this->vehiclesForUser($auth),
            'types' => EnergyLog::TYPES,
            'fuelKinds' => EnergyLog::FUEL_KINDS,
            'chargerTypes' => EnergyLog::CHARGER_TYPES,
            'defaultVehicleId' => Sqid::decodeOrNumeric(Vehicle::class, $request->query('vehicle')),
        ]);
    }

    public function store(SaveEnergyLogRequest $request): RedirectResponse {
        Gate::authorize('create', EnergyLog::class);

        $data = $request->validated();
        /** @var User $auth */
        $auth = Auth::user();
        $data['user_id'] = $auth->id;
        $data['organization_id'] = $auth->organization_id;

        $this->service->create($data);

        return redirect()->route('energy-logs.index')
            ->with('success', __('Tankung/Ladung erfasst.'));
    }

    public function edit(EnergyLog $energyLog): View {
        Gate::authorize('update', $energyLog);

        /** @var User $auth */
        $auth = Auth::user();

        return view('energy-logs._form_dialog', [
            'log' => $energyLog,
            'vehicles' => $this->vehiclesForUser($auth),
            'types' => EnergyLog::TYPES,
            'fuelKinds' => EnergyLog::FUEL_KINDS,
            'chargerTypes' => EnergyLog::CHARGER_TYPES,
            'defaultVehicleId' => $energyLog->vehicle_id,
        ]);
    }

    public function update(SaveEnergyLogRequest $request, EnergyLog $energyLog): RedirectResponse {
        Gate::authorize('update', $energyLog);

        $this->service->update($energyLog, $request->validated());

        return redirect()->route('energy-logs.index')
            ->with('success', __('Eintrag aktualisiert.'));
    }

    public function destroy(EnergyLog $energyLog): RedirectResponse {
        Gate::authorize('delete', $energyLog);

        $this->service->delete($energyLog);

        return redirect()->route('energy-logs.index')
            ->with('success', __('Eintrag gelöscht.'));
    }

    /**
     * Mirrors WorkBalanceReportController: admins may pick any user via ?user=,
     * non-admins are locked to themselves; ?user=all (admins only) returns null
     * so all users are shown.
     */
    private function resolveTargetUser(Request $request, User $authUser): ?User {
        return $this->resolveRequestedUserOrAll(
            $request,
            $authUser,
            'Nur Admins dürfen alle Nutzer einsehen.',
            'Nur Admins dürfen Tankungen anderer Nutzer einsehen.',
        );
    }

    /** @return Collection<int, Vehicle> */
    private function vehiclesForUser(User $user): Collection {
        $query = Vehicle::query()->active()->orderBy('label')->orderBy('license_plate');
        if (! $user->isAdmin()) {
            $query->forUser((int) $user->id);
        }

        /** @var Collection<int, Vehicle> $list */
        $list = $query->get();

        return $list;
    }

    /** @return Collection<int, User> */
    private function loadSelectableUsers(): Collection {
        /** @var Collection<int, User> $users */
        $users = User::query()->orderBy('name')->get();

        return $users;
    }
}
