<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisOfflineBundleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Crisis;

use App\Models\Crisis\{CrisisAction, CrisisCase, CrisisRole, CrisisSituationReport, CrisisTeamAssignment};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-914: Offline-Krisenmappe — Bündel der aktiven Krisen. */
final class CrisisOfflineBundleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_bundle_contains_active_crises_with_situation_actions_and_team(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id, 'mobile' => '+49 170 1234567']);
        $active = CrisisCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Serverausfall', 'category' => 'security', 'severity' => 'critical', 'status' => 'activated', 'created_by' => $admin->id]);
        CrisisCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Alte Krise', 'category' => 'security', 'severity' => 'low', 'status' => 'closed', 'created_by' => $admin->id]);
        $role = CrisisRole::query()->create(['organization_id' => $this->organization->id, 'name' => 'Leitung Krisenstab']);
        CrisisTeamAssignment::query()->create(['organization_id' => $this->organization->id, 'crisis_case_id' => $active->id, 'crisis_role_id' => $role->id, 'user_id' => $admin->id]);
        CrisisSituationReport::query()->create(['organization_id' => $this->organization->id, 'crisis_case_id' => $active->id, 'version' => 1, 'content' => 'Rechenzentrum ohne Strom', 'created_by' => $admin->id]);
        CrisisAction::query()->create(['organization_id' => $this->organization->id, 'crisis_case_id' => $active->id, 'title' => 'Notstrom prüfen', 'priority' => 'high', 'status' => 'open']);

        $this->actingAs($admin)->get(route('crisis.index'))->assertOk()->assertSee('data-offline-crisis="' . route('crisis.offline-bundle') . '"', false);

        $json = $this->actingAs($admin)->getJson(route('crisis.offline-bundle'))->assertOk()->json();
        $this->assertSame(['Serverausfall'], array_column($json['cases'], 'title'));
        $case = $json['cases'][0];
        $this->assertSame('Rechenzentrum ohne Strom', $case['situation']['content']);
        $this->assertSame(['Notstrom prüfen'], array_column($case['actions'], 'title'));
        $this->assertSame('Leitung Krisenstab', $case['team'][0]['role']);
        $this->assertSame('+49 170 1234567', $case['team'][0]['person']['phone']);
    }

    public function test_offline_page_loads_the_reader_without_inline_handlers(): void {
        $html = \CommonToolkit\Helper\FileSystem\File::read(public_path('offline.html'));
        $this->assertStringContainsString('src="/offline-reader.js"', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringContainsString('"/offline-reader.js"', \CommonToolkit\Helper\FileSystem\File::read(public_path('sw.js')));
    }
}
