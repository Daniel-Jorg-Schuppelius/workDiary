<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocalTimesToUtcMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Asset, Customer, Room, Vehicle};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-823: Bestandszeiten der Ortszeit-Module wandern nach UTC — je Zeile in
 * der Zeitzone der Organisation, sommerzeitgenau. Werte, die schon UTC waren
 * (Importe, now()-Zeitpunkte, Calendly), bleiben; down() stellt her.
 */
class LocalTimesToUtcMigrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private int $userId;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->userId = (int) $this->orgUser()->id;
    }

    private function migration(): Migration {
        return require database_path('migrations/2027_02_22_101100_convert_local_times_to_utc.php');
    }

    private function raw(string $table, int $id, string $column): ?string {
        $value = DB::table($table)->where('id', $id)->value($column);

        return $value === null ? null : (string) $value;
    }

    /** @param array<string, mixed> $attributes */
    private function event(array $attributes): int {
        return (int) DB::table('events')->insertGetId($attributes + [
            'organization_id' => $this->organization->id,
            'title' => 'Schulung',
            'timezone' => 'UTC',
        ]);
    }

    public function test_events_follow_their_zone_and_imports_stay(): void {
        $summer = $this->event(['started_at' => '2030-07-10 10:00:00', 'ended_at' => '2030-07-10 12:00:00']);
        $winter = $this->event(['started_at' => '2030-01-10 10:00:00', 'ended_at' => '2030-01-10 12:00:00']);
        $newYork = $this->event(['started_at' => '2030-07-10 10:00:00', 'ended_at' => '2030-07-10 11:00:00', 'timezone' => 'America/New_York']);
        $imported = $this->event(['started_at' => '2030-07-10 08:00:00', 'ended_at' => '2030-07-10 09:00:00']);
        $importedChild = $this->event(['started_at' => '2030-07-17 08:00:00', 'ended_at' => '2030-07-17 09:00:00', 'series_id' => $imported]);
        DB::table('integration_inbox_items')->insert([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'caldav',
            'target_type' => 'event',
            'external_type' => 'calendar_event',
            'dedupe_key' => 'calendar-proposal:1',
            'case_type' => 'unmatched',
            'status' => 'resolved_created',
            'remote_snapshot' => '{}',
            'resolved_to_type' => 'App\\Models\\Event',
            'resolved_to_id' => $imported,
        ]);
        $room = Room::factory()->create(['organization_id' => $this->organization->id]);
        $pivot = (int) DB::table('event_room')->insertGetId([
            'event_id' => $summer, 'room_id' => $room->id,
            'started_at' => '2030-07-10 10:00:00', 'ended_at' => '2030-07-10 12:00:00',
        ]);

        $migration = $this->migration();
        $migration->up();

        $this->assertSame('2030-07-10 08:00:00', $this->raw('events', $summer, 'started_at'));
        $this->assertSame('2030-01-10 09:00:00', $this->raw('events', $winter, 'started_at'));
        $this->assertSame('2030-07-10 14:00:00', $this->raw('events', $newYork, 'started_at'));
        $this->assertSame('2030-07-10 08:00:00', $this->raw('event_room', $pivot, 'started_at'));
        $this->assertSame('2030-07-10 08:00:00', $this->raw('events', $imported, 'started_at'));
        $this->assertSame('2030-07-17 08:00:00', $this->raw('events', $importedChild, 'started_at'));

        $migration->down();

        $this->assertSame('2030-07-10 10:00:00', $this->raw('events', $summer, 'started_at'));
        $this->assertSame('2030-07-10 12:00:00', $this->raw('event_room', $pivot, 'ended_at'));
        $this->assertSame('2030-07-10 08:00:00', $this->raw('events', $imported, 'started_at'));
    }

    public function test_maintenance_end_set_by_now_stays(): void {
        $insert = fn(string $status, string $endsAt, ?string $updatedAt = null): int => (int) DB::table('maintenance_windows')->insertGetId([
            'scope' => 'organization',
            'organization_id' => $this->organization->id,
            'announce_from' => '2030-07-09 18:00:00',
            'starts_at' => '2030-07-10 22:00:00',
            'ends_at' => $endsAt,
            'status' => $status,
            'updated_at' => $updatedAt,
        ]);
        $planned = $insert('planned', '2030-07-10 23:00:00');
        $completedByScan = $insert('completed', '2030-07-10 23:00:00', '2030-07-10 21:01:00');
        $completedByHand = $insert('completed', '2030-07-10 20:40:12', '2030-07-10 20:40:12');
        $rolledBack = $insert('rolled_back', '2030-07-10 20:45:00', '2030-07-10 20:45:00');

        $this->migration()->up();

        $this->assertSame('2030-07-10 20:00:00', $this->raw('maintenance_windows', $planned, 'starts_at'));
        $this->assertSame('2030-07-09 16:00:00', $this->raw('maintenance_windows', $planned, 'announce_from'));
        $this->assertSame('2030-07-10 21:00:00', $this->raw('maintenance_windows', $planned, 'ends_at'));
        $this->assertSame('2030-07-10 21:00:00', $this->raw('maintenance_windows', $completedByScan, 'ends_at'));
        $this->assertSame('2030-07-10 20:40:12', $this->raw('maintenance_windows', $completedByHand, 'ends_at'));
        $this->assertSame('2030-07-10 20:45:00', $this->raw('maintenance_windows', $rolledBack, 'ends_at'));
        $this->assertSame('2030-07-10 20:00:00', $this->raw('maintenance_windows', $rolledBack, 'starts_at'));
    }

    public function test_rental_reservations_follow_the_case_only_while_they_carry_its_times(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $case = (int) DB::table('rental_cases')->insertGetId([
            'organization_id' => $this->organization->id, 'number' => 'V-1', 'customer_id' => $customer->id,
            'starts_at' => '2030-07-10 08:00:00', 'ends_at' => '2030-07-12 18:00:00',
        ]);
        $reservation = fn(?int $caseId, string $from, string $to): int => (int) DB::table('rental_reservations')->insertGetId([
            'organization_id' => $this->organization->id, 'rental_case_id' => $caseId, 'asset_id' => $asset->id,
            'starts_at' => $from, 'ends_at' => $to,
        ]);
        $hard = $reservation($case, '2030-07-10 08:00:00', '2030-07-12 18:00:00');
        $swapped = $reservation($case, '2030-07-11 07:13:44', '2030-07-12 18:00:00');
        $block = $reservation(null, '2030-07-20 08:00:00', '2030-07-20 12:00:00');
        $request = (int) DB::table('rental_requests')->insertGetId([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id,
            'starts_at' => '2030-07-10 08:00:00', 'ends_at' => '2030-07-12 18:00:00',
        ]);

        $migration = $this->migration();
        $migration->up();

        $this->assertSame('2030-07-10 06:00:00', $this->raw('rental_cases', $case, 'starts_at'));
        $this->assertSame('2030-07-10 06:00:00', $this->raw('rental_reservations', $hard, 'starts_at'));
        $this->assertSame('2030-07-11 07:13:44', $this->raw('rental_reservations', $swapped, 'starts_at'));
        $this->assertSame('2030-07-12 16:00:00', $this->raw('rental_reservations', $swapped, 'ends_at'));
        $this->assertSame('2030-07-20 06:00:00', $this->raw('rental_reservations', $block, 'starts_at'));
        $this->assertSame('2030-07-10 06:00:00', $this->raw('rental_requests', $request, 'starts_at'));

        $migration->down();

        $this->assertSame('2030-07-10 08:00:00', $this->raw('rental_reservations', $hard, 'starts_at'));
        $this->assertSame('2030-07-11 07:13:44', $this->raw('rental_reservations', $swapped, 'starts_at'));
        $this->assertSame('2030-07-12 18:00:00', $this->raw('rental_reservations', $swapped, 'ends_at'));
        $this->assertSame('2030-07-12 18:00:00', $this->raw('rental_cases', $case, 'ends_at'));
    }

    public function test_only_portal_appointments_and_their_unbilled_entries_move(): void {
        $entry = fn(?string $invoicedAt): int => (int) DB::table('diary_entries')->insertGetId([
            'organization_id' => $this->organization->id, 'user_id' => $this->userId, 'content' => 'Termin',
            'start_at' => '2030-07-10 09:00:00', 'end_at' => '2030-07-10 10:00:00', 'invoiced_at' => $invoicedAt,
        ]);
        $request = fn(string $source, string $uri, ?int $entryId): int => (int) DB::table('appointment_requests')->insertGetId([
            'organization_id' => $this->organization->id, 'source' => $source, 'source_uri' => $uri, 'diary_entry_id' => $entryId,
            'start_at' => '2030-07-10 09:00:00', 'end_at' => '2030-07-10 10:00:00',
        ]);
        $openEntry = $entry(null);
        $billedEntry = $entry('2030-07-31 12:00:00');
        $portal = $request('portal', 'portal:1', $openEntry);
        $portalBilled = $request('portal', 'portal:2', $billedEntry);
        $calendly = $request('calendly', 'https://api.calendly.com/x', null);

        $this->migration()->up();

        $this->assertSame('2030-07-10 07:00:00', $this->raw('appointment_requests', $portal, 'start_at'));
        $this->assertSame('2030-07-10 07:00:00', $this->raw('diary_entries', $openEntry, 'start_at'));
        $this->assertSame('2030-07-10 08:00:00', $this->raw('diary_entries', $openEntry, 'end_at'));
        $this->assertSame('2030-07-10 07:00:00', $this->raw('appointment_requests', $portalBilled, 'start_at'));
        $this->assertSame('2030-07-10 09:00:00', $this->raw('diary_entries', $billedEntry, 'start_at'));
        $this->assertSame('2030-07-10 09:00:00', $this->raw('appointment_requests', $calendly, 'start_at'));
    }

    public function test_reservations_and_deadlines_move(): void {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $vehicleReservation = (int) DB::table('vehicle_reservations')->insertGetId([
            'organization_id' => $this->organization->id, 'vehicle_id' => $vehicle->id, 'reserved_by_user_id' => $this->userId,
            'reserved_from' => '2030-12-01 07:30:00', 'reserved_to' => '2030-12-01 16:00:00',
        ]);
        $assignment = (int) DB::table('asset_assignments')->insertGetId([
            'organization_id' => $this->organization->id, 'asset_id' => $asset->id,
            'checked_out_at' => '2030-07-01 06:00:00', 'expected_return_at' => '2030-07-15 17:00:00',
        ]);
        $problem = (int) DB::table('problems')->insertGetId([
            'organization_id' => $this->organization->id, 'title' => 'Wiederkehrender Ausfall',
            'effectiveness_check_due_at' => '2030-08-01 09:00:00',
        ]);
        $tender = (int) DB::table('application_opportunities')->insertGetId([
            'organization_id' => $this->organization->id, 'title' => 'Ausschreibung', 'opening_at' => '2030-09-01 10:00:00',
        ]);

        $this->migration()->up();

        $this->assertSame('2030-12-01 06:30:00', $this->raw('vehicle_reservations', $vehicleReservation, 'reserved_from'));
        $this->assertSame('2030-07-15 15:00:00', $this->raw('asset_assignments', $assignment, 'expected_return_at'));
        $this->assertSame('2030-07-01 06:00:00', $this->raw('asset_assignments', $assignment, 'checked_out_at'));
        $this->assertSame('2030-08-01 07:00:00', $this->raw('problems', $problem, 'effectiveness_check_due_at'));
        $this->assertSame('2030-09-01 08:00:00', $this->raw('application_opportunities', $tender, 'opening_at'));
    }
}
