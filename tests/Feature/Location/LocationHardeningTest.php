<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationHardeningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Location;

use App\Enums\Location\{LocationPendingEntryStatus, LocationVisitStatus};
use App\Http\Controllers\Api\LocationController;
use App\Jobs\Location\{ImportGoogleTimeline, ProcessLocationBatch};
use App\Models\Customer\Customer;
use App\Models\Location\{CustomerGeofence, LocationPendingEntry, LocationPoint, LocationVisit};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Location\{VisitBuilder, VisitMaterializer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Härtung der Standorterfassung (Phase 146, MVP-1186). */
class LocationHardeningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private CustomerGeofence $geofence;

    private const LAT = 52.5200000;

    private const LNG = 13.4050000;

    /** ≈ 1 m in Grad Breite. */
    private const DEG_PER_M = 1 / 111_320;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->geofence = CustomerGeofence::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'label' => 'Hauptsitz',
            'center_lat' => self::LAT,
            'center_lng' => self::LNG,
            'radius_m' => 100,
            'min_dwell_minutes' => 5,
            'gap_merge_minutes' => 10,
            'is_active' => true,
        ]);
    }

    private function point(float $northMeters, int $minuteOffset, ?int $accuracy = 10): void {
        LocationPoint::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'recorded_at' => Carbon::parse('2026-06-29 08:00:00')->addMinutes($minuteOffset),
            'lat' => self::LAT + $northMeters * self::DEG_PER_M,
            'lng' => self::LNG,
            'accuracy_m' => $accuracy,
            'source' => LocationPoint::SOURCE_OWNTRACKS,
        ]);
    }

    public function test_entry_needs_distance_plus_accuracy_within_radius(): void {
        // 50 m vom Mittelpunkt, aber 70 m Unsicherheit → kein sicherer Eintritt.
        foreach ([0, 5, 10, 15, 20] as $offset) {
            $this->point(50, $offset, accuracy: 70);
        }
        $this->point(2000, 40);

        app(VisitBuilder::class)->rebuildForUser($this->user);

        $this->assertSame(0, LocationVisit::query()->count());
    }

    public function test_exit_needs_distance_minus_accuracy_beyond_radius(): void {
        // Drinnen, dann Punkte am Rand (130 m, Genauigkeit 50 → 80 m sicher) halten den Besuch.
        foreach ([0, 5, 10] as $offset) {
            $this->point(0, $offset);
        }
        foreach ([15, 20, 25, 30] as $offset) {
            $this->point(130, $offset, accuracy: 50);
        }
        $this->point(2000, 50); // eindeutig weg

        app(VisitBuilder::class)->rebuildForUser($this->user);

        $visit = LocationVisit::query()->sole();
        $this->assertSame(LocationVisitStatus::Closed, $visit->status);
        $this->assertSame(30, $visit->duration_min);
        $this->assertSame(7, $visit->sample_count);
    }

    public function test_late_points_rebuild_unconfirmed_visits(): void {
        foreach ([10, 15, 20, 25, 30] as $offset) {
            $this->point(0, $offset);
        }
        $this->point(2000, 45);
        app(VisitBuilder::class)->rebuildForUser($this->user);
        $this->assertSame(20, LocationVisit::query()->sole()->duration_min);

        // Offline-Puffer liefert zwei frühere Punkte nach → der Besuch beginnt früher.
        $this->point(0, 0);
        $this->point(0, 5);
        app(VisitBuilder::class)->rebuildForUser($this->user);

        $visit = LocationVisit::query()->sole();
        $this->assertSame(30, $visit->duration_min);
        $this->assertSame('2026-06-29 08:00:00', $visit->entered_at->toDateTimeString());
        $this->assertSame(0, LocationPoint::query()->whereNull('processed_at')->count());
    }

    public function test_late_points_leave_confirmed_visits_alone(): void {
        foreach ([10, 15, 20, 25, 30] as $offset) {
            $this->point(0, $offset);
        }
        $this->point(2000, 45);
        app(VisitBuilder::class)->rebuildForUser($this->user);
        app(VisitMaterializer::class)->materializeForUser($this->user);
        $entry = LocationPendingEntry::query()->sole();
        app(VisitMaterializer::class)->confirm($entry, $this->user);

        $this->point(0, 0);
        app(VisitBuilder::class)->rebuildForUser($this->user);

        $this->assertSame(1, LocationVisit::query()->count());
        $this->assertSame(20, LocationVisit::query()->sole()->duration_min);
        $this->assertSame(LocationPendingEntryStatus::Imported, $entry->fresh()->status);
        $this->assertSame(0, LocationPoint::query()->whereNull('processed_at')->count());
    }

    public function test_stale_open_visit_is_closed_and_proposed(): void {
        foreach ([0, 5, 10, 15, 20, 25, 30] as $offset) {
            $this->point(0, $offset);
        }
        app(VisitBuilder::class)->rebuildForUser($this->user);
        $this->assertSame(LocationVisitStatus::Open, LocationVisit::query()->sole()->status);

        Carbon::setTestNow('2026-06-29 12:00:00');
        $this->assertSame(0, app(VisitBuilder::class)->closeStaleVisits(6)); // erst 3,5 h ohne Punkt
        Carbon::setTestNow('2026-06-29 15:00:00');
        $this->assertSame(1, app(VisitBuilder::class)->closeStaleVisits(6));

        $visit = LocationVisit::query()->sole();
        $this->assertSame(LocationVisitStatus::Closed, $visit->status);
        $this->assertSame(30, $visit->duration_min);
        $this->assertSame(1, LocationPendingEntry::query()->count());
    }

    public function test_close_stale_visits_command_uses_config(): void {
        foreach ([0, 5, 10] as $offset) {
            $this->point(0, $offset);
        }
        app(VisitBuilder::class)->rebuildForUser($this->user);
        Carbon::setTestNow('2026-06-30 08:00:00');

        $this->artisan('location:close-stale-visits')->assertSuccessful();

        $this->assertSame(LocationVisitStatus::Closed, LocationVisit::query()->sole()->status);
    }

    public function test_batch_job_runs_once_per_user_at_a_time(): void {
        $middleware = (new ProcessLocationBatch($this->user->id))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_default_project_is_created_on_confirm_not_in_the_job(): void {
        // Der Kunden-Observer legt beim Anlegen ein Standardprojekt an — hier ist es weg (gelöscht).
        $this->customer->projects()->delete();
        $this->assertNull($this->customer->defaultProject());
        foreach ([0, 5, 10, 15, 20, 25, 30] as $offset) {
            $this->point(0, $offset);
        }
        $this->point(2000, 45);
        app(VisitBuilder::class)->rebuildForUser($this->user);
        app(VisitMaterializer::class)->materializeForUser($this->user);

        $entry = LocationPendingEntry::query()->sole();
        $this->assertNull($entry->project_id);
        $this->assertNull($this->customer->fresh()->defaultProject());

        $timeEntry = app(VisitMaterializer::class)->confirm($entry, $this->user);

        $project = $this->customer->fresh()->defaultProject();
        $this->assertInstanceOf(Project::class, $project);
        $this->assertSame($project->id, $timeEntry->project_id);
        $this->assertSame($project->id, $entry->fresh()->project_id);
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_google_import_runs_as_queued_job(): void {
        config(['license.feature_overrides' => [LocationController::MODULE => true]]);
        app(FeatureFlagResolver::class)->flush();
        Queue::fake();
        $json = json_encode(['locations' => [
            ['latitudeE7' => 525200000, 'longitudeE7' => 134050000, 'timestamp' => '2026-06-20T08:00:00Z', 'accuracy' => 12],
        ]]);

        $this->actingAs($this->user)
            ->post(route('location.devices.import-google'), ['file' => UploadedFile::fake()->createWithContent('Records.json', (string) $json)])
            ->assertRedirect(route('location.devices.index'));

        Queue::assertPushed(ImportGoogleTimeline::class, fn(ImportGoogleTimeline $job): bool => $job->userId === $this->user->id);
        $this->assertSame(0, LocationPoint::query()->count());
    }
}
