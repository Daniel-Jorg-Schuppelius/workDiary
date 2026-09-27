<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectorTourTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\AssetCompliance;

use App\Models\Asset\Asset;
use App\Models\AssetCompliance\{AssetComplianceProfile, AssetInspectionSchedule};
use App\Models\Diary\{DiaryEntry, Tour};
use App\Models\Platform\User;
use App\Services\AssetCompliance\AssetComplianceService;
use Database\Seeders\AssetComplianceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** MVP-918: fällige Prüftermine eines Prüfers als optimierte Tour. */
final class InspectorTourTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $inspector;

    /** @var array<string, AssetInspectionSchedule> */
    private array $schedules = [];

    protected function setUp(): void {
        parent::setUp();
        // OSRM nicht erreichbar → Luftlinie (ohne echte Netzabfrage).
        FakePluginHttp::fake(['*' => FakePluginHttp::response(['code' => 'Error'], 503)]);
        $this->travelTo('2026-10-05 09:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->seed(AssetComplianceCatalogSeeder::class);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->inspector = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Paula Prüfer', 'home_address' => 'Start', 'home_lat' => 52.5, 'home_lng' => 13.0]);
        $profile = AssetComplianceProfile::query()->whereNull('organization_id')->where('code', 'calibration_annual')->firstOrFail();
        $service = app(AssetComplianceService::class);
        // Luftlinie ab 13,0° Ost: nah (13,1) vor mittel (13,2) vor fern (13,4).
        foreach (['fern' => ['13.4', '2026-10-08'], 'nah' => ['13.1', '2026-10-09'], 'mittel' => ['13.2', '2026-10-10'], 'spaet' => ['13.3', '2026-12-01']] as $name => [$lng, $due]) {
            $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Waage ' . $name, 'location_text' => 'Halle ' . $name, 'location_lat' => '52.5', 'location_lng' => $lng]);
            $assignment = $service->assign($profile, $asset, $this->admin, []);
            $this->schedules[$name] = AssetInspectionSchedule::query()->create(['organization_id' => $this->organization->id, 'asset_compliance_assignment_id' => $assignment->id, 'asset_id' => $asset->id, 'due_on' => $due, 'inspector_user_id' => $this->inspector->id, 'status' => 'planned']);
        }
    }

    public function test_due_inspections_become_an_optimized_tour(): void {
        $this->actingAs($this->admin)->get(route('asset-compliance.tours.index', ['inspector' => $this->inspector->sqid, 'until' => '2026-10-31']))->assertOk()
            ->assertSee('Waage fern')->assertSee('Waage nah')->assertDontSee('Waage spaet');

        $ids = array_map(fn (string $n): string => $this->schedules[$n]->sqid, ['fern', 'nah', 'mittel']);
        $response = $this->actingAs($this->admin)->post(route('asset-compliance.tours.store'), ['inspector_user_id' => $this->inspector->sqid, 'date' => '2026-10-07', 'until' => '2026-10-31', 'schedule_ids' => $ids]);

        $tour = Tour::query()->sole();
        $response->assertRedirect(route('tours.show', $tour));
        $this->assertSame($this->inspector->id, $tour->user_id);
        $this->assertSame('2026-10-07', $tour->tour_date?->toDateString());
        $this->assertSame(['Halle nah', 'Halle mittel', 'Halle fern'], $tour->orderedStops()->pluck('address_line')->all());

        $schedule = $this->schedules['nah']->fresh();
        $this->assertNotNull($schedule->diary_entry_id);
        $this->assertSame('2026-10-07', $schedule->planned_on?->toDateString());
        $entry = DiaryEntry::query()->findOrFail($schedule->diary_entry_id);
        $this->assertSame($this->inspector->id, $entry->assigned_user_id);
        $this->assertSame($schedule->asset_id, $entry->asset_id);

        // Verplante Termine erscheinen nicht erneut.
        $this->actingAs($this->admin)->get(route('asset-compliance.tours.index', ['inspector' => $this->inspector->sqid, 'until' => '2026-10-31']))->assertOk()->assertDontSee('Waage nah');
    }

    public function test_foreign_or_late_schedules_are_ignored(): void {
        $this->actingAs($this->admin)->post(route('asset-compliance.tours.store'), ['inspector_user_id' => $this->inspector->sqid, 'date' => '2026-10-07', 'until' => '2026-10-31', 'schedule_ids' => [$this->schedules['spaet']->sqid]])
            ->assertSessionHas('error', __('inspection_tour.none_selected'));
        $this->assertSame(0, Tour::query()->count());
    }

    public function test_planning_needs_the_compliance_permission(): void {
        $this->actingAs($this->inspector)->get(route('asset-compliance.tours.index'))->assertForbidden();
    }
}
