<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubResourceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Asset\AssetBlockReason;
use App\Enums\Club\ClubEventVisibility;
use App\Enums\User\UserRole;
use App\Models\{Asset, Room};
use App\Models\Calendar\Event;
use App\Models\Club\{ClubGroup, ClubMember, ClubResource, ClubResourceBooking};
use App\Models\Platform\User;
use App\Services\Asset\AssetBlockService;
use App\Services\Club\{ClubEventService, ClubResourceService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sportstätten und Ressourcen (Feature 159, MVP-853): Ganzhalle kollidiert mit
 * Tischbelegung; getrennte freie Tische parallel buchbar; Tennisplatz und
 * Hallendrittel getrennt buchbar; Boot mit Wartungssperre nicht buchbar; zwei
 * konkurrierende Buchungen nicht beide bestätigt; gescheiterte Verschiebung
 * erhält alte Buchung.
 */
class ClubResourceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ClubGroup $group;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->group = ClubGroup::factory()->create(['name' => 'Tischtennis Herren']);
    }

    private function resources(): ClubResourceService {
        return app(ClubResourceService::class);
    }

    /** @param  array<string, mixed>  $attributes */
    private function resource(string $name, string $kind = 'hall', array $attributes = []): ClubResource {
        return $this->resources()->create($this->organization, ['name' => $name, 'kind' => $kind] + $attributes);
    }

    /** @param  array<string, mixed>  $overrides */
    private function event(string $start, string $end, array $overrides = []): Event {
        return app(ClubEventService::class)->create($this->organization, $this->admin, array_merge([
            'title' => 'Training ' . $start,
            'kind' => 'training',
            'visibility' => ClubEventVisibility::Groups->value,
            'club_group_ids' => [$this->group->id],
            'started_at' => CarbonImmutable::parse($start, 'Europe/Berlin')->utc()->format('Y-m-d H:i:s'),
            'ended_at' => CarbonImmutable::parse($end, 'Europe/Berlin')->utc()->format('Y-m-d H:i:s'),
            'timezone' => 'Europe/Berlin',
        ], $overrides));
    }

    public function test_whole_hall_conflicts_with_a_table_booking_in_both_directions(): void {
        $room = Room::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Sporthalle']);
        $hall = $this->resource('Sporthalle', 'hall', ['room_id' => $room->id]);
        $table1 = $this->resource('Tisch 1', 'table', ['parent_id' => $hall->id]);
        $table2 = $this->resource('Tisch 2', 'table', ['parent_id' => $hall->id]);

        $tt = $this->event('2026-10-05 18:00', '2026-10-05 20:00');
        $this->resources()->book($tt, $table1, [], $this->admin);

        // Ganze Halle als Ressource: kollidiert mit dem Tisch.
        $gym = $this->event('2026-10-05 19:00', '2026-10-05 21:00');
        try {
            $this->resources()->book($gym, $hall, [], $this->admin);
            $this->fail('Ganzhalle kollidiert mit Tischbelegung.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        // Ganze Halle über den Raumkalender (event_room): ebenfalls Konflikt.
        try {
            $this->event('2026-10-05 19:00', '2026-10-05 21:00', ['room_id' => $room->id]);
            $this->fail('Raumbuchung der Halle kollidiert mit Tischbelegung.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }
        // Umgekehrt: Halle über Raum belegt → Tisch 2 später nicht buchbar, Tisch 2 vorher schon.
        $this->resources()->book($tt, $table2, [], $this->admin);
        $late = $this->event('2026-10-05 21:00', '2026-10-05 22:00', ['room_id' => $room->id]);
        $this->assertSame(1, $late->rooms()->count());
        $lateTt = $this->event('2026-10-05 21:30', '2026-10-05 22:30');
        try {
            $this->resources()->book($lateTt, $table2, [], $this->admin);
            $this->fail('Tisch unter der raumbelegten Halle nicht buchbar.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Sporthalle', implode(' ', $e->errors()['club_resource_id']), 'Der Konflikt kommt vom Raumkalender der übergeordneten Halle.');
        }
    }

    public function test_free_tables_and_separate_facilities_are_bookable_in_parallel(): void {
        $hall = $this->resource('Halle', 'hall');
        $third1 = $this->resource('Drittel A', 'part', ['parent_id' => $hall->id]);
        $third2 = $this->resource('Drittel B', 'part', ['parent_id' => $hall->id]);
        $table1 = $this->resource('Tisch 1', 'table', ['parent_id' => $third1->id]);
        $table2 = $this->resource('Tisch 2', 'table', ['parent_id' => $third1->id]);
        $court = $this->resource('Tennisplatz 1', 'court');

        $a = $this->event('2026-10-06 18:00', '2026-10-06 20:00');
        $b = $this->event('2026-10-06 18:00', '2026-10-06 20:00');
        $c = $this->event('2026-10-06 18:00', '2026-10-06 20:00');
        $this->resources()->book($a, $table1, [], $this->admin);
        $this->resources()->book($b, $table2, [], $this->admin);
        $this->resources()->book($c, $third2, [], $this->admin);
        $this->resources()->book($c, $court, [], $this->admin);
        $this->assertSame(4, ClubResourceBooking::query()->count(), 'Zwei Tische, anderes Drittel und Tennisplatz parallel.');

        $d = $this->event('2026-10-06 19:00', '2026-10-06 21:00');
        try {
            $this->resources()->book($d, $third1, [], $this->admin);
            $this->fail('Drittel A ist über seine Tische belegt.');
        } catch (ValidationException $e) {
            $this->assertCount(2, $e->errors()['club_resource_id']);
        }
        try {
            $this->resources()->book($d, $hall, [], $this->admin);
            $this->fail('Ganze Halle ist über Tische und Drittel B belegt.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        $this->resources()->book($d, $court, ['starts_at' => CarbonImmutable::parse('2026-10-06 20:00', 'Europe/Berlin')->utc()->format('Y-m-d H:i:s'), 'ends_at' => CarbonImmutable::parse('2026-10-06 21:00', 'Europe/Berlin')->utc()->format('Y-m-d H:i:s')], $this->admin);
        $this->assertSame(5, ClubResourceBooking::query()->count(), 'Tennisplatz nach Ende der ersten Belegung frei.');
    }

    public function test_boat_with_maintenance_block_and_missing_clearance_cannot_be_booked(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Vierer ohne']);
        $boat = $this->resource('Vierer ohne', 'boat', ['asset_id' => $asset->id, 'requires_clearance' => true]);
        $trip = $this->event('2026-10-07 09:00', '2026-10-07 12:00');
        $rower = ClubMember::factory()->aged(30)->create();

        $block = app(AssetBlockService::class)->block($asset, AssetBlockReason::Maintenance, $this->admin, 'Riemen defekt');
        try {
            $this->resources()->book($trip, $boat, [], $this->admin);
            $this->fail('Boot mit Wartungssperre nicht buchbar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        app(AssetBlockService::class)->release($block, $this->admin);
        try {
            $this->resources()->book($trip, $boat, ['club_member_id' => $rower->id], $this->admin);
            $this->fail('Ohne Einweisungsfreigabe keine Nutzung.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_member_id', $e->errors());
        }
        $this->resources()->grantClearance($boat, $rower, $this->admin, CarbonImmutable::parse('2027-12-31'));
        $booking = $this->resources()->book($trip, $boat, ['club_member_id' => $rower->id], $this->admin);
        $this->assertSame($rower->id, $booking->club_member_id);
        $this->assertTrue($this->resources()->hasClearance($boat, $rower, CarbonImmutable::parse('2026-10-07')));
        $this->assertFalse($this->resources()->hasClearance($boat, $rower, CarbonImmutable::parse('2028-01-01')), 'Befristung greift.');
    }

    public function test_competing_bookings_are_never_both_confirmed_and_capacity_units_are_counted(): void {
        $lanes = $this->resource('Schwimmbahnen', 'lane', ['capacity' => 4]);
        $one = $this->event('2026-10-08 17:00', '2026-10-08 18:00');
        $two = $this->event('2026-10-08 17:00', '2026-10-08 18:00');
        $three = $this->event('2026-10-08 17:30', '2026-10-08 18:30');
        $this->resources()->book($one, $lanes, ['quantity' => 2], $this->admin);
        $this->resources()->book($two, $lanes, ['quantity' => 2], $this->admin);
        try {
            $this->resources()->book($three, $lanes, ['quantity' => 1], $this->admin);
            $this->fail('Kapazität erschöpft — die konkurrierende Buchung wird nicht bestätigt.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        $this->assertSame(2, ClubResourceBooking::query()->where('club_resource_id', $lanes->id)->count());

        // Exklusive Ressource: dieselbe Buchung zweimal (Wettlauf) — nur eine bleibt.
        $table = $this->resource('Tisch 1', 'table');
        $this->resources()->book($one, $table, [], $this->admin);
        try {
            $this->resources()->book($two, $table, [], $this->admin);
            $this->fail('Zweite konkurrierende Buchung abgewiesen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        // Puffer zählen mit: 15 Minuten Abbau blockieren den direkten Anschluss.
        $next = $this->event('2026-10-08 18:00', '2026-10-08 19:00');
        $one->refresh();
        ClubResourceBooking::query()->where('event_id', $one->id)->where('club_resource_id', $table->id)->update(['teardown_minutes' => 15]);
        try {
            $this->resources()->book($next, $table, [], $this->admin);
            $this->fail('Abbaupuffer blockiert den Anschluss.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        $later = $this->event('2026-10-08 18:15', '2026-10-08 19:00');
        $this->resources()->book($later, $table, [], $this->admin);
        $this->assertSame(2, ClubResourceBooking::query()->where('club_resource_id', $table->id)->count());
    }

    public function test_failed_move_keeps_the_old_booking_and_time_and_cancellation_releases(): void {
        $table = $this->resource('Tisch 1', 'table');
        $a = $this->event('2026-10-09 18:00', '2026-10-09 20:00');
        $b = $this->event('2026-10-09 20:00', '2026-10-09 22:00');
        $this->resources()->book($a, $table, [], $this->admin);
        $this->resources()->book($b, $table, [], $this->admin);

        $newStart = CarbonImmutable::parse('2026-10-09 20:00', 'Europe/Berlin')->utc();
        try {
            app(ClubEventService::class)->update($a, $this->admin, ['kind' => 'training', 'title' => $a->title, 'started_at' => $newStart->format('Y-m-d H:i:s'), 'ended_at' => $newStart->addHours(2)->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin', 'club_group_ids' => [$this->group->id]]);
            $this->fail('Verschiebung in belegte Zeit scheitert.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        $a->refresh();
        $this->assertSame('2026-10-09 16:00:00', CarbonImmutable::instance($a->started_at)->utc()->format('Y-m-d H:i:s'), 'Alte Zeit bleibt.');
        $booking = ClubResourceBooking::query()->where('event_id', $a->id)->firstOrFail();
        $this->assertSame('2026-10-09 16:00:00', $booking->starts_at->utc()->format('Y-m-d H:i:s'), 'Alte Belegung bleibt.');

        // Verschiebung in freie Zeit nimmt die Belegung mit.
        $free = CarbonImmutable::parse('2026-10-09 15:00', 'Europe/Berlin')->utc();
        app(ClubEventService::class)->update($a, $this->admin, ['kind' => 'training', 'title' => $a->title, 'started_at' => $free->format('Y-m-d H:i:s'), 'ended_at' => $free->addHours(2)->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin', 'club_group_ids' => [$this->group->id]]);
        $this->assertSame('2026-10-09 13:00:00', $booking->refresh()->starts_at->utc()->format('Y-m-d H:i:s'));

        app(ClubEventService::class)->cancel($b, $this->admin, 'Ausfall');
        $this->assertSame(0, ClubResourceBooking::query()->where('event_id', $b->id)->count(), 'Absage gibt Belegungen frei.');
    }

    public function test_closure_flags_existing_bookings_blocks_new_ones_and_reopening_unflags(): void {
        $pitch = $this->resource('Rasenplatz', 'pitch');
        $training = $this->event('2026-10-10 17:00', '2026-10-10 19:00');
        $booking = $this->resources()->book($training, $pitch, [], $this->admin);

        $closure = $this->resources()->close($pitch, CarbonImmutable::parse('2026-10-10 00:00', 'Europe/Berlin')->utc(), CarbonImmutable::parse('2026-10-11 00:00', 'Europe/Berlin')->utc(), 'Platz unbespielbar (Regen)', $this->admin);
        $this->assertNotNull($booking->refresh()->flagged_at, 'Bestehende Belegung zur Neuplanung markiert, nicht gelöscht.');
        $this->assertStringContainsString('Regen', (string) $booking->flag_reason);
        $other = $this->event('2026-10-10 19:00', '2026-10-10 21:00');
        try {
            $this->resources()->book($other, $pitch, [], $this->admin);
            $this->fail('Sperrzeit verhindert neue Belegung.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Regen', implode(' ', $e->errors()['club_resource_id']));
        }
        $this->resources()->reopen($closure, $this->admin);
        $this->assertNull($booking->refresh()->flagged_at);
        $this->resources()->book($other, $pitch, [], $this->admin);
    }

    public function test_pages_dialogs_and_rights(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->group->update(['leader_user_id' => $lead->id]);
        $hall = $this->resource('Halle', 'hall');
        $table = $this->resource('Tisch 1', 'table', ['parent_id' => $hall->id]);
        $event = $this->event('2026-10-12 18:00', '2026-10-12 20:00');

        $this->actingAs($this->admin)->get(route('club.resources.index'))->assertOk()->assertSee('Tisch 1');
        $this->actingAs($this->admin)->get(route('club.resources.create'))->assertOk();
        $this->actingAs($this->admin)->post(route('club.resources.store'), ['name' => 'Tisch 2', 'kind' => 'table', 'parent_id' => $hall->sqid, 'capacity' => 1])->assertRedirect();
        $this->assertSame($hall->id, ClubResource::query()->where('name', 'Tisch 2')->firstOrFail()->parent_id);
        $this->actingAs($this->admin)->get(route('club.resources.show', $table))->assertOk()->assertSee(__('club.resources.card.bookings'));
        $this->actingAs($this->admin)->get(route('club.resources.edit', $table))->assertOk();
        $this->actingAs($this->admin)->get(route('club.resources.closures.create', $table))->assertOk();
        $this->actingAs($this->admin)->get(route('club.resources.clearances.create', $table))->assertOk();
        $this->actingAs($this->admin)->get(route('club.events.show', $event))->assertOk()->assertSee(__('club.resources.card.event'));
        $this->actingAs($this->admin)->get(route('club.events.resources.create', $event))->assertOk();

        $this->actingAs($lead)->post(route('club.events.resources.store', $event), ['club_resource_id' => $table->sqid, 'quantity' => 1])->assertRedirect(route('club.events.show', $event));
        $booking = ClubResourceBooking::query()->where('event_id', $event->id)->firstOrFail();
        $this->actingAs($lead)->get(route('club.events.show', $event))->assertOk()->assertSee('Tisch 1');
        $this->actingAs($lead)->get(route('club.resources.index'))->assertOk();
        $this->actingAs($lead)->get(route('club.resources.create'))->assertForbidden();
        $this->actingAs($lead)->post(route('club.resources.close', $table), ['starts_at' => '2026-10-12T00:00', 'ends_at' => '2026-10-13T00:00', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($lead)->delete(route('club.events.resources.destroy', [$event, $booking]))->assertRedirect();
        $this->assertSame(0, ClubResourceBooking::query()->count());
        $this->actingAs($this->orgUser())->get(route('club.resources.index'))->assertForbidden();
    }
}
