<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FacilityDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Facility\Demo;

use App\Enums\Demo\DemoIndustry;
use App\Enums\Facility\{RoomRequirementKind, RoomUsageType};
use App\Models\Facility\{Building, Floor, Room, RoomRequirement, Site};
use App\Models\Platform\Organization;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/**
 * Liegenschaften zur Vorführung (MVP-1533): der Standort des Hauptkunden mit
 * Gebäude, zwei Etagen und sechs Räumen, der Technikraum mit
 * Zutrittsbeschränkung. Die Anschrift ist erfunden, der Ort stammt aus der
 * Musterbranche.
 */
final class FacilityDemoBlock implements DemoBlock {
    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $admin = $context->users->first();
        $customer = $context->mainCustomer;
        if (! $context->moduleActive('module.liegenschaften') || $admin === null || $customer === null) {
            return ['sites' => 0, 'rooms' => 0];
        }
        $organization = $context->organization;
        /** @var list<array{name: string, city: string}> $places */
        $places = $context->blueprint['customers'];

        $site = Site::query()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'name' => (string) __('demo.facility.site_name', ['customer' => $customer->name]),
            'code' => 'S1',
            'address_street' => (string) __('demo.facility.street'),
            'address_zip' => '12345',
            'address_city' => $places[0]['city'] ?? null,
            'country' => 'DE',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $building = Building::query()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'name' => (string) __('demo.facility.building'),
            'code' => 'A',
            'gross_area_m2' => 1850,
            'year_built' => 1998,
            'created_by' => $admin->id,
        ]);

        $technical = $context->industry === DemoIndustry::ItService ? RoomUsageType::ServerRoom : RoomUsageType::Technical;
        $rooms = 0;
        foreach ([
            0 => ['ground', [
                ['reception', 'A.0.01', RoomUsageType::TrafficArea, '42.00', null],
                ['technical', 'A.0.02', $technical, '18.50', null],
                ['storage', 'A.0.03', RoomUsageType::Storage, '64.00', null],
            ]],
            1 => ['first', [
                ['office', 'A.1.01', RoomUsageType::Office, '28.00', 4],
                ['meeting', 'A.1.02', RoomUsageType::Meeting, '35.00', 12],
                ['social', 'A.1.03', RoomUsageType::Social, '22.00', 8],
            ]],
        ] as $level => [$floorKey, $roomSpecs]) {
            $floor = Floor::query()->create([
                'organization_id' => $organization->id,
                'building_id' => $building->id,
                'level' => $level,
                'label' => (string) __('demo.facility.floor.' . $floorKey),
                'created_by' => $admin->id,
            ]);
            foreach ($roomSpecs as [$roomKey, $code, $usage, $area, $capacity]) {
                $room = Room::query()->create([
                    'organization_id' => $organization->id,
                    'floor_id' => $floor->id,
                    'customer_id' => $customer->id,
                    'name' => (string) __('demo.facility.room.' . $roomKey),
                    'code' => $code,
                    'usage_type' => $usage,
                    'net_area_m2' => $area,
                    'capacity' => $capacity,
                    'is_active' => true,
                    'created_by' => $admin->id,
                ]);
                $rooms++;
                if ($roomKey === 'technical') {
                    RoomRequirement::query()->create([
                        'organization_id' => $organization->id,
                        'room_id' => $room->id,
                        'kind' => RoomRequirementKind::AccessRestriction,
                        'note' => (string) __('demo.facility.access_note'),
                        'is_active' => true,
                        'created_by' => $admin->id,
                    ]);
                }
            }
        }

        return ['sites' => 1, 'rooms' => $rooms];
    }

    public function purge(Organization $organization): void {
        RoomRequirement::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        Room::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        Floor::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        Building::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        Site::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }
}
