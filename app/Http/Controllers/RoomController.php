<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Facility\{RoomRequirementKind, RoomUsageType};
use App\Models\{Building, CleaningProfile, Customer, Floor, Room, Site};
use App\Services\Event\RoomBookingService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoomController extends Controller {
    private const ALLOWED_SORTS = ['name', 'code', 'capacity', 'is_active'];

    public function index(Request $request, RoomBookingService $bookings): View {
        Gate::authorize('viewAny', Room::class);

        $view = $request->query('view', 'list');
        $day = $request->query('day') ? Carbon::parse((string) $request->query('day')) : Carbon::today();

        $search = $request->string('q')->toString();
        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'name';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $rooms = Room::query()
            ->with(['requirements' => fn($q) => $q->where('is_active', true)])
            ->when($search !== '', fn($q) => $q->where(function ($w) use ($search): void {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('building', 'like', "%{$search}%")
                    ->orWhere('floor', 'like', "%{$search}%");
            }))
            ->orderBy($sort, $dir)
            ->paginate(50)
            ->withQueryString();
        $grid = $view === 'grid' ? $bookings->gridForDay($day) : [];
        $gridRooms = $view === 'grid' ? Room::query()->active()->orderBy('name')->get() : collect();

        return view('rooms.index', [
            'rooms' => $rooms,
            'view' => $view,
            'day' => $day,
            'grid' => $grid,
            'gridRooms' => $gridRooms,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', Room::class);

        return view('rooms._form_dialog', array_merge(
            [
                'room' => null,
                'isEdit' => false,
                'prefill' => $this->resolvePrefill($request),
            ],
            $this->pickerData(),
        ));
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Room::class);
        Room::create($this->validateRoom($request));

        return redirect()->route('rooms.index')->with('success', __('Raum angelegt.'));
    }

    public function edit(Room $room): View {
        Gate::authorize('update', $room);

        $room->load('requirements');

        return view('rooms._form_dialog', array_merge(
            [
                'room' => $room,
                'isEdit' => true,
                'prefill' => [],
                'requirementKinds' => RoomRequirementKind::options(),
            ],
            $this->pickerData(),
        ));
    }

    public function update(Request $request, Room $room): RedirectResponse {
        Gate::authorize('update', $room);
        $room->update($this->validateRoom($request));

        return redirect()->route('rooms.index')->with('success', __('Raum aktualisiert.'));
    }

    public function destroy(Room $room): RedirectResponse {
        Gate::authorize('delete', $room);
        $room->delete();

        return redirect()->route('rooms.index')->with('success', __('Raum gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validateRoom(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:32'],
            'building' => ['nullable', 'string', 'max:120'],
            'floor' => ['nullable', 'string', 'max:32'],
            'floor_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('floors')],
            'customer_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'cleaning_profile_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('cleaning_profiles')],
            'usage_type' => ['nullable', 'string', new \Illuminate\Validation\Rules\Enum(RoomUsageType::class)],
            'net_area_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'max:40'],
            'color' => ['nullable', 'string', 'max:9'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['is_active'] ??= false;
        $data['usage_type'] = $data['usage_type'] ?? RoomUsageType::Office->value;

        return $data;
    }

    /** @return array<string, mixed> */
    private function pickerData(): array {
        return [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'customer_id']),
            'buildings' => Building::query()->orderBy('name')->get(['id', 'name', 'site_id']),
            'floors' => Floor::query()->orderBy('level')->get(['id', 'label', 'level', 'building_id']),
            'cleaningProfiles' => CleaningProfile::query()->orderBy('label')->get(['id', 'code', 'label']),
            'usageTypes' => collect(RoomUsageType::cases())->mapWithKeys(
                fn(RoomUsageType $t): array => [$t->value => $t->label()],
            )->all(),
        ];
    }

    /**
     * Leitet die Picker-Vorbelegung (Customer/Site/Building/Floor) aus den
     * Query-Parametern ab. Akzeptiert wahlweise ?floor=, ?building=, ?site=
     * oder ?customer=; höhere Ebenen werden aus der Beziehung der niedrigsten
     * gesetzten Ebene aufgefüllt.
     *
     * @return array{customer_id: int|null, site_id: int|null, building_id: int|null, floor_id: int|null}
     */
    private function resolvePrefill(Request $request): array {
        $rawFloor = (string) $request->query('floor', '');
        $rawBuilding = (string) $request->query('building', '');
        $rawSite = (string) $request->query('site', '');
        $rawCustomer = (string) $request->query('customer', '');

        $floorId = Sqid::decodeOrNumeric(Floor::class, $rawFloor);
        $buildingId = Sqid::decodeOrNumeric(Building::class, $rawBuilding);
        $siteId = Sqid::decodeOrNumeric(Site::class, $rawSite);
        $customerId = Sqid::decodeOrNumeric(Customer::class, $rawCustomer);

        if ($floorId !== null) {
            $floor = Floor::query()->with('building.site')->find($floorId);
            if ($floor !== null) {
                $buildingId ??= $floor->building_id;
                $siteId ??= $floor->building?->site_id;
                $customerId ??= $floor->building?->site?->customer_id;
            }
        }
        if ($buildingId !== null && $siteId === null) {
            $building = Building::query()->with('site')->find($buildingId);
            if ($building !== null) {
                $siteId = $building->site_id;
                $customerId ??= $building->site?->customer_id;
            }
        }
        if ($siteId !== null && $customerId === null) {
            $customerId = Site::query()->whereKey($siteId)->value('customer_id');
        }

        return [
            'customer_id' => $customerId,
            'site_id'     => $siteId,
            'building_id' => $buildingId,
            'floor_id'    => $floorId,
        ];
    }
}
