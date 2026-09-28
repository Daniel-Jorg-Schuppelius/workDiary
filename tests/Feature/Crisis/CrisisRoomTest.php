<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisRoomTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Crisis;

use App\Models\Asset\Asset;
use App\Models\Crisis\{CrisisCase, CrisisMapPoint};
use App\Models\Platform\User;
use App\Services\Crisis\CrisisRoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-963: Anwesenheit im Krisenraum und Lagekarte. */
final class CrisisRoomTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_presence_heartbeat_and_map_markers(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id, 'name' => 'Stabsleitung']);
        $case = CrisisCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Hochwasser', 'category' => 'infrastructure', 'severity' => 'major', 'status' => 'reported', 'created_by' => $admin->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pumpwerk Süd', 'location_lat' => '50.9375000', 'location_lng' => '6.9603000']);

        $this->actingAs($admin)->postJson(route('crisis.room.heartbeat', $case))->assertOk()->assertJsonPath('present.0.name', 'Stabsleitung');

        $this->actingAs($admin)->post(route('crisis.links.store', $case), ['linkable_type' => 'asset', 'linkable_sqid' => $asset->sqid])->assertRedirect();
        $this->actingAs($admin)->post(route('crisis.room.points.store', $case), ['label' => 'Sammelpunkt Rathaus', 'kind' => 'assembly', 'lat' => '50.94', 'lng' => '6.95'])->assertSessionHas('status');

        $markers = app(CrisisRoomService::class)->markers($case->fresh());
        $this->assertSame(['Pumpwerk Süd', 'Sammelpunkt Rathaus'], array_column($markers, 'label'));

        $this->actingAs($admin)->get(route('crisis.show', $case))->assertOk()->assertSeeText(__('crisis.room.title'))->assertSeeText('Sammelpunkt Rathaus');
        $this->actingAs($admin)->delete(route('crisis.room.points.destroy', CrisisMapPoint::query()->sole()))->assertSessionHas('status');
        $this->assertSame(0, CrisisMapPoint::query()->count());

        $this->travel(5)->minutes();
        $this->assertSame([], app(CrisisRoomService::class)->present($case));
    }
}
