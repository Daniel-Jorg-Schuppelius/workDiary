<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeofenceWebTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Location;

use App\Http\Controllers\Api\LocationController;
use App\Models\Customer;
use App\Models\Location\{CustomerGeofence, LocationDeviceToken, LocationPendingEntry, LocationVisit};
use App\Models\{TimeEntry, User};
use App\Services\Location\VisitMaterializer;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class GeofenceWebTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(); // Org-Factory: Plan enterprise → Modul aktiv
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function geofence(array $attributes = []): CustomerGeofence {
        return CustomerGeofence::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'label' => 'Zentrale',
            'center_lat' => 52.52,
            'center_lng' => 13.405,
            'radius_m' => 100,
            'min_dwell_minutes' => 5,
            'gap_merge_minutes' => 10,
            'is_active' => true,
        ], $attributes));
    }

    public function test_index_is_reachable(): void {
        $this->geofence();

        $this->actingAs($this->admin)->get(route('geofences.index'))
            ->assertOk()
            ->assertViewHas('geofences');
    }

    public function test_store_creates_geofence(): void {
        $response = $this->actingAs($this->admin)->post(route('geofences.store'), [
            'customer_id' => Sqid::encode(Customer::class, $this->customer->id),
            'label' => 'Baustelle West',
            'center_lat' => 52.5,
            'center_lng' => 13.4,
            'radius_m' => 150,
            'min_dwell_minutes' => 5,
            'gap_merge_minutes' => 10,
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_geofences', [
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'label' => 'Baustelle West',
            'radius_m' => 150,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_update_and_destroy(): void {
        $geofence = $this->geofence();

        $this->actingAs($this->admin)->put(route('geofences.update', $geofence), [
            'customer_id' => Sqid::encode(Customer::class, $this->customer->id),
            'label' => 'Umbenannt',
            'center_lat' => 52.52,
            'center_lng' => 13.405,
            'radius_m' => 200,
            'min_dwell_minutes' => 3,
            'gap_merge_minutes' => 8,
        ])->assertRedirect();

        $this->assertDatabaseHas('customer_geofences', ['id' => $geofence->id, 'label' => 'Umbenannt', 'radius_m' => 200]);

        $this->actingAs($this->admin)->delete(route('geofences.destroy', $geofence))->assertRedirect();
        $this->assertDatabaseMissing('customer_geofences', ['id' => $geofence->id]);
    }

    private function pendingEntry(): LocationPendingEntry {
        $geofence = $this->geofence();
        LocationVisit::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'customer_geofence_id' => $geofence->id,
            'entered_at' => '2026-06-29 08:00:00',
            'left_at' => '2026-06-29 10:00:00',
            'duration_min' => 120,
            'sample_count' => 5,
            'status' => LocationVisit::STATUS_CLOSED,
            'materialized' => false,
        ]);
        app(VisitMaterializer::class)->materializeForUser($this->admin);

        return LocationPendingEntry::query()->firstOrFail();
    }

    public function test_review_confirm_creates_time_entry(): void {
        $entry = $this->pendingEntry();

        $this->actingAs($this->admin)->post(route('location.review.confirm', $entry))->assertRedirect();

        $entry->refresh();
        $this->assertSame(LocationPendingEntry::STATUS_IMPORTED, $entry->status);
        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertSame(120, TimeEntry::query()->firstOrFail()->minutes);
    }

    public function test_review_dismiss(): void {
        $entry = $this->pendingEntry();

        $this->actingAs($this->admin)->post(route('location.review.dismiss', $entry))->assertRedirect();

        $entry->refresh();
        $this->assertSame(LocationPendingEntry::STATUS_DISMISSED, $entry->status);
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_review_rejects_foreign_entry(): void {
        $entry = $this->pendingEntry();
        $other = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($other)->post(route('location.review.confirm', $entry))->assertForbidden();
    }

    public function test_device_store_sets_opt_in_and_returns_url(): void {
        $response = $this->actingAs($this->admin)->post(route('location.devices.store'), ['label' => 'Diensthandy']);

        $response->assertRedirect();
        $response->assertSessionHas('location_device_url');
        $this->assertTrue((bool) $this->admin->fresh()->getPreference(LocationController::OPT_IN_PREFERENCE, false));
        $this->assertSame(1, LocationDeviceToken::query()->where('user_id', $this->admin->id)->count());
    }

    public function test_device_consent_toggle_and_revoke(): void {
        $this->actingAs($this->admin)->post(route('location.devices.consent'), ['enabled' => 1])->assertRedirect();
        $this->assertTrue((bool) $this->admin->fresh()->getPreference(LocationController::OPT_IN_PREFERENCE, false));

        [$device] = LocationDeviceToken::issue($this->admin->fresh(), 'X');
        $this->actingAs($this->admin)->delete(route('location.devices.destroy', $device))->assertRedirect();
        $this->assertNotNull($device->fresh()->revoked_at);
    }
}
