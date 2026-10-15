<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TripProposalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Location;

use App\Enums\Location\LocationPendingTripStatus;
use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Models\Customer\Customer;
use App\Models\Location\{CustomerGeofence, LocationPendingTrip, LocationPoint, LocationVisit};
use App\Models\Platform\User;
use App\Models\Travel\TravelLog;
use App\Services\Location\Contracts\{NullTrackDistanceSource, TrackDistanceSource};
use App\Services\Location\VisitBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Fahrtvorschläge aus der Spur zwischen zwei Besuchen (Phase 146, MVP-1185). */
class TripProposalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private const LAT = 52.5200000;

    private const LNG = 13.4050000;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        // Ohne OSRM zählt die Spurlänge (Null-Bindung des Contracts).
        $this->app->instance(TrackDistanceSource::class, new NullTrackDistanceSource());
    }

    private function zone(string $label, float $lng): CustomerGeofence {
        return CustomerGeofence::create([
            'organization_id' => $this->organization->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id])->id,
            'label' => $label,
            'center_lat' => self::LAT,
            'center_lng' => $lng,
            'radius_m' => 100,
            'min_dwell_minutes' => 5,
            'gap_merge_minutes' => 10,
            'is_active' => true,
        ]);
    }

    private function point(float $lng, int $minuteOffset): void {
        LocationPoint::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'recorded_at' => Carbon::parse('2026-06-29 08:00:00')->addMinutes($minuteOffset),
            'lat' => self::LAT,
            'lng' => $lng,
            'accuracy_m' => 10,
            'source' => LocationPoint::SOURCE_OWNTRACKS,
        ]);
    }

    /** Besuch A (08:00–08:30), Fahrt über zwei Zwischenpunkte, Besuch B (08:45–09:15), danach weg. */
    private function drive(): void {
        $this->zone('A', self::LNG);
        $this->zone('B', self::LNG + 0.03); // ≈ 2 km östlich
        foreach ([0, 5, 10, 15, 20, 25, 30] as $offset) {
            $this->point(self::LNG, $offset);
        }
        $this->point(self::LNG + 0.01, 35);
        $this->point(self::LNG + 0.02, 40);
        foreach ([45, 50, 55, 60, 65, 70, 75] as $offset) {
            $this->point(self::LNG + 0.03, $offset);
        }
        $this->point(self::LNG + 0.3, 90); // endgültig weg

        app(VisitBuilder::class)->rebuildForUser($this->user);
    }

    public function test_trip_between_two_visits_is_proposed_from_track(): void {
        $this->drive();

        $this->assertSame(2, LocationVisit::query()->count());
        $trips = LocationPendingTrip::query()->get();
        $this->assertCount(1, $trips);

        $trip = $trips->first();
        $this->assertSame('A', $trip->from_label);
        $this->assertSame('B', $trip->to_label);
        $this->assertSame('2026-06-29 08:30:00', $trip->started_at->toDateTimeString());
        $this->assertSame('2026-06-29 08:45:00', $trip->ended_at->toDateTimeString());
        $this->assertSame(4, $trip->point_count); // letzter Punkt in A, zwei dazwischen, erster in B
        $this->assertSame(LocationPendingTrip::SOURCE_TRACK, $trip->distance_source);
        $this->assertEqualsWithDelta(2.03, (float) $trip->distance_km, 0.1);
        $this->assertSame(LocationPendingTripStatus::Open, $trip->status);
    }

    public function test_rebuild_does_not_duplicate_the_trip(): void {
        $this->drive();
        $this->point(self::LNG + 0.3, 120);
        app(VisitBuilder::class)->rebuildForUser($this->user);

        $this->assertSame(1, LocationPendingTrip::query()->count());
    }

    public function test_confirm_books_a_travel_log_with_vehicle_and_purpose(): void {
        $this->drive();
        $trip = LocationPendingTrip::query()->firstOrFail();

        $this->actingAs($this->user)->post(route('location.review.trips.confirm', $trip), [
            'vehicle' => TravelLogVehicle::Company->value,
            'trip_kind' => TripKind::Business->value,
            'purpose' => 'Kundenbesuch B',
        ])->assertRedirect(route('location.review.trips.index'));

        $trip->refresh();
        $this->assertSame(LocationPendingTripStatus::Imported, $trip->status);
        $log = TravelLog::query()->firstOrFail();
        $this->assertSame($log->id, $trip->travel_log_id);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('Kundenbesuch B', $log->purpose);
        $this->assertSame('A', $log->from_address);
        $this->assertSame('B', $log->to_address);
        $this->assertEqualsWithDelta((float) $trip->distance_km, (float) $log->distance_km, 0.001);
        $this->assertSame(15, $log->duration_minutes);
        $this->assertStringContainsString('Kartenabgleich', (string) $log->notes);
    }

    public function test_confirm_requires_vehicle_kind(): void {
        $this->drive();
        $trip = LocationPendingTrip::query()->firstOrFail();

        $this->actingAs($this->user)->from(route('location.review.trips.index'))
            ->post(route('location.review.trips.confirm', $trip), ['trip_kind' => 'business'])
            ->assertSessionHasErrors('vehicle');
        $this->assertSame(0, TravelLog::query()->count());
    }

    public function test_dismiss_marks_trip_without_travel_log(): void {
        $this->drive();
        $trip = LocationPendingTrip::query()->firstOrFail();

        $this->actingAs($this->user)->post(route('location.review.trips.dismiss', $trip))->assertRedirect();

        $this->assertSame(LocationPendingTripStatus::Dismissed, $trip->fresh()->status);
        $this->assertSame(0, TravelLog::query()->count());
    }

    public function test_foreign_user_cannot_see_or_book_the_trip(): void {
        $this->drive();
        $trip = LocationPendingTrip::query()->firstOrFail();
        $other = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($other)->get(route('location.review.trips.index'))->assertOk()->assertDontSee('Kundenbesuch');
        $this->actingAs($other)->get(route('location.review.trips.confirm.form', $trip))->assertForbidden();
        $this->actingAs($other)->post(route('location.review.trips.confirm', $trip), ['vehicle' => 'company', 'trip_kind' => 'business'])->assertForbidden();
        $this->actingAs($other)->post(route('location.review.trips.dismiss', $trip))->assertForbidden();
    }

    public function test_index_lists_open_trips_with_dialog_link(): void {
        $this->drive();
        $trip = LocationPendingTrip::query()->firstOrFail();

        $this->actingAs($this->user)->get(route('location.review.trips.index'))
            ->assertOk()
            ->assertSee(route('location.review.trips.confirm.form', $trip), false)
            ->assertSee('2,03 km');
        $this->actingAs($this->user)->get(route('location.review.trips.confirm.form', $trip))
            ->assertOk()
            ->assertSee('name="vehicle"', false);
    }
}
