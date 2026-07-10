<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyRoomFacilityMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Facility;

use App\Models\{Customer, Room};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Validiert die Datenmigration der Legacy-Spalten rooms.building / rooms.floor
 * in die neue FM-Hierarchie (Site → Building → Floor).
 */
class LegacyRoomFacilityMigrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_migration_links_legacy_rooms_to_floor_hierarchy(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $roomId = DB::table('rooms')->insertGetId([
            'organization_id' => $this->organization->id,
            'customer_id'     => $customer->id,
            'name'            => 'Raum A',
            'building'        => 'Verwaltung',
            'floor'           => '1. OG',
            'usage_type'      => 'office',
            'is_active'       => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->runLegacyMigration();

        $room = Room::query()->findOrFail($roomId);
        $this->assertNotNull($room->floor_id);

        $floor = $room->floorRelation;
        $this->assertNotNull($floor);
        $this->assertSame(1, $floor->level);
        $this->assertSame('Verwaltung', $floor->building->name);
        $this->assertSame('Standort', $floor->building->site->name);
        $this->assertSame($customer->id, $floor->building->site->customer_id);
    }

    public function test_migration_is_idempotent_and_reuses_hierarchy(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        foreach (['Raum A', 'Raum B'] as $name) {
            DB::table('rooms')->insert([
                'organization_id' => $this->organization->id,
                'customer_id'     => $customer->id,
                'name'            => $name,
                'building'        => 'Hauptgebäude',
                'floor'           => 'EG',
                'usage_type'      => 'office',
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        $this->runLegacyMigration();
        $this->runLegacyMigration();

        $this->assertSame(1, DB::table('sites')->where('customer_id', $customer->id)->count());
        $this->assertSame(1, DB::table('buildings')->where('name', 'Hauptgebäude')->count());
        $this->assertSame(1, DB::table('floors')->where('level', 0)->count());
        $this->assertSame(2, Room::query()->whereNotNull('floor_id')->count());
    }

    public function test_migration_skips_rooms_without_customer(): void {
        $roomId = DB::table('rooms')->insertGetId([
            'organization_id' => $this->organization->id,
            'customer_id'     => null,
            'name'            => 'Waise',
            'building'        => 'X',
            'floor'           => 'EG',
            'usage_type'      => 'office',
            'is_active'       => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->runLegacyMigration();

        $this->assertNull(Room::query()->findOrFail($roomId)->floor_id);
    }

    private function runLegacyMigration(): void {
        $migration = require database_path(
            'migrations/2026_07_14_150000_migrate_legacy_room_facility_strings.php'
        );
        $migration->up();
    }
}
