<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationImportStampTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Location;

use App\Http\Controllers\Api\LocationController;
use App\Models\Location\LocationPoint;
use App\Models\User;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LocationImportStampTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        config(['license.feature_overrides' => [LocationController::MODULE => true]]);
        app(FeatureFlagResolver::class)->flush();
    }

    public function test_browser_stamp_stores_point(): void {
        $this->user->setPreference(LocationController::OPT_IN_PREFERENCE, true);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/location/stamp', ['lat' => 52.52, 'lng' => 13.405, 'accuracy_m' => 8])
            ->assertOk()
            ->assertJson(['stored' => 1]);

        $this->assertSame(1, LocationPoint::query()->where('source', LocationPoint::SOURCE_BROWSER)->count());
    }

    public function test_browser_stamp_requires_opt_in(): void {
        $this->user->setPreference(LocationController::OPT_IN_PREFERENCE, false);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/location/stamp', ['lat' => 52.52, 'lng' => 13.405])
            ->assertStatus(403);

        $this->assertSame(0, LocationPoint::query()->count());
    }

    public function test_google_records_import(): void {
        $json = json_encode(['locations' => [
            ['latitudeE7' => 525200000, 'longitudeE7' => 134050000, 'timestamp' => '2026-06-20T08:00:00Z', 'accuracy' => 12],
            ['latitudeE7' => 525201000, 'longitudeE7' => 134050000, 'timestamp' => '2026-06-20T08:05:00Z', 'accuracy' => 9],
            ['latitudeE7' => 0, 'longitudeE7' => 0], // ohne Zeit → verworfen
        ]]);

        $file = UploadedFile::fake()->createWithContent('Records.json', $json);

        $this->actingAs($this->user)
            ->post(route('location.devices.import-google'), ['file' => $file])
            ->assertRedirect();

        $this->assertSame(2, LocationPoint::query()->where('source', LocationPoint::SOURCE_GOOGLE)->count());
        $this->assertTrue((bool) $this->user->fresh()->getPreference(LocationController::OPT_IN_PREFERENCE, false));
    }

    public function test_google_semantic_import(): void {
        $json = json_encode(['semanticSegments' => [[
            'timelinePath' => [
                ['point' => '52.5200000°, 13.4050000°', 'time' => '2026-06-20T09:00:00Z'],
                ['point' => '52.5210000°, 13.4050000°', 'time' => '2026-06-20T09:10:00Z'],
            ],
        ]]]);

        $file = UploadedFile::fake()->createWithContent('timeline.json', $json);

        $this->actingAs($this->user)
            ->post(route('location.devices.import-google'), ['file' => $file])
            ->assertRedirect();

        $this->assertSame(2, LocationPoint::query()->where('source', LocationPoint::SOURCE_GOOGLE)->count());
    }
}
