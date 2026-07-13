<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFormOptions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Enums\Asset\{AssetClass, AssetStatus, MaintenanceIntervalKind};
use App\Models\{Building, Customer, Floor, ForeignCustomer, Room, Site};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Options-/Formulardaten für die Asset-Masken (Label-Maps, Kunden-/
 * Facility-Sammlungen, Picker-Vorbelegung). Aus dem AssetController
 * extrahiert (Refactoring Welle 2, B6b) — von Controller und
 * {@see AssetDetailAssembler} gemeinsam genutzt.
 */
class AssetFormOptions {
    /**
     * @return array<string, string>
     */
    public function classOptions(): array {
        return [
            AssetClass::Device->value => __('Gerät'),
            AssetClass::Machine->value => __('Maschine'),
            AssetClass::Tool->value => __('Werkzeug'),
            AssetClass::Vehicle->value => __('Fahrzeug'),
            AssetClass::Installation->value => __('Installation'),
            AssetClass::Software->value => __('Software'),
            AssetClass::Other->value => __('Sonstiges'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array {
        return [
            AssetStatus::Active->value => __('Aktiv'),
            AssetStatus::InMaintenance->value => __('In Wartung'),
            AssetStatus::InRepair->value => __('In Reparatur'),
            AssetStatus::Blocked->value => __('Gesperrt'),
            AssetStatus::Reserved->value => __('Reserviert'),
            AssetStatus::LoanOut->value => __('Ausgeliehen'),
            AssetStatus::Replaced->value => __('Ersetzt'),
            AssetStatus::Decommissioned->value => __('Außer Betrieb'),
            AssetStatus::Lost->value => __('Verloren'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function statusOptionsForCreate(): array {
        return [
            AssetStatus::Active->value => __('Aktiv'),
            AssetStatus::InMaintenance->value => __('In Wartung'),
            AssetStatus::InRepair->value => __('In Reparatur'),
            AssetStatus::Blocked->value => __('Gesperrt'),
            AssetStatus::Reserved->value => __('Reserviert'),
            AssetStatus::LoanOut->value => __('Ausgeliehen'),
            AssetStatus::Replaced->value => __('Ersetzt'),
            AssetStatus::Lost->value => __('Verloren'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function intervalKindOptions(): array {
        return collect(MaintenanceIntervalKind::cases())
            ->mapWithKeys(fn(MaintenanceIntervalKind $k): array => [$k->value => match ($k) {
                MaintenanceIntervalKind::Days => __('Tage'),
                MaintenanceIntervalKind::Weeks => __('Wochen'),
                MaintenanceIntervalKind::Months => __('Monate'),
                MaintenanceIntervalKind::OperatingHours => __('Betriebsstunden'),
                MaintenanceIntervalKind::Kilometers => __('Kilometer'),
            }])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function customerOptions(): array {
        return Customer::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return Collection<int, ForeignCustomer>
     */
    public function foreignCustomerOptions(): Collection {
        return ForeignCustomer::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);
    }

    /**
     * @return array<string, string>
     */
    public function categoryOptions(): array {
        /** @var array<string, string> $pool */
        $pool = (array) config('asset_categories', []);

        return $pool;
    }

    /**
     * Liefert die Sammlungen für den Facility-Picker.
     *
     * @return array{sites: Collection<int, Site>, buildings: Collection<int, Building>, floors: Collection<int, Floor>, rooms: Collection<int, Room>}
     */
    public function facilityData(): array {
        return [
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'customer_id']),
            'buildings' => Building::query()->orderBy('name')->get(['id', 'name', 'site_id']),
            'floors' => Floor::query()->orderBy('level')->get(['id', 'label', 'level', 'building_id']),
            'rooms' => Room::query()->orderBy('name')->get(['id', 'name', 'floor_id', 'customer_id']),
        ];
    }

    /**
     * Leitet die Picker-Vorbelegung (Customer/Site/Building/Floor/Room) aus den
     * Query-Parametern ab. Akzeptiert ?room=, ?floor=, ?building=, ?site=
     * oder ?customer=; höhere Ebenen werden aufgefüllt.
     *
     * @return array{customer_id: int|null, foreign_customer_id: int|null, site_id: int|null, building_id: int|null, floor_id: int|null, room_id: int|null}
     */
    public function resolvePrefill(Request $request): array {
        $rawRoom = (string) $request->query('room', '');
        $rawFloor = (string) $request->query('floor', '');
        $rawBuilding = (string) $request->query('building', '');
        $rawSite = (string) $request->query('site', '');
        $rawCustomer = (string) $request->query('customer', '');

        $roomId = Sqid::decodeOrNumeric(Room::class, $rawRoom);
        $floorId = Sqid::decodeOrNumeric(Floor::class, $rawFloor);
        $buildingId = Sqid::decodeOrNumeric(Building::class, $rawBuilding);
        $siteId = Sqid::decodeOrNumeric(Site::class, $rawSite);
        $customerId = Sqid::decodeOrNumeric(Customer::class, $rawCustomer);

        if ($roomId !== null) {
            $room = Room::query()->with('floorRelation.building.site')->find($roomId);
            if ($room !== null) {
                $floorId ??= $room->floor_id;
                $buildingId ??= $room->floorRelation?->building_id;
                $siteId ??= $room->floorRelation?->building?->site_id;
                $customerId ??= $room->customer_id ?? $room->floorRelation?->building?->site?->customer_id;
            }
        }
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
            'foreign_customer_id' => null,
            'site_id' => $siteId,
            'building_id' => $buildingId,
            'floor_id' => $floorId,
            'room_id' => $roomId,
        ];
    }
}
