<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyTomTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\{ControllerRole, MeasureCategory, ReviewResult};
use App\Models\{Organization, User};
use App\Models\Privacy\TechnicalMeasure;
use App\Services\Privacy\{DataProtectionPermissions, ProcessingActivityService, TechnicalMeasureService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** TOM-Katalog (Art. 32): Lifecycle, Zuordnung, Wirksamkeitsprüfung, VVT-Snapshot. */
class PrivacyTomTest extends TestCase {
    use RefreshDatabase;

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function officer(Organization $org): User {
        DataProtectionPermissions::seedOrganization($org);
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);

        return $user;
    }

    public function test_officer_can_open_catalog(): void {
        $org = Organization::factory()->create();
        $this->actingAs($this->officer($org))->get(route('dataprotection.tom.index'))->assertOk();
    }

    public function test_free_plan_gated(): void {
        $org = Organization::factory()->free()->create();
        $this->actingAs($this->officer($org))->get(route('dataprotection.tom.index'))->assertStatus(423);
    }

    public function test_measure_versioning_and_review(): void {
        $org = Organization::factory()->create();
        $actor = User::factory()->create(['organization_id' => $org->id]);
        $svc = app(TechnicalMeasureService::class);

        $measure = $svc->createDraft($org, 'Festplattenverschlüsselung', MeasureCategory::DataAccess, ['description' => 'LUKS'], $actor);
        $v1 = $measure->versions()->first();
        $svc->approve($measure, $v1, $actor);
        $this->assertSame($v1->id, $measure->fresh()->current_version_id);

        $svc->recordReview($measure, ReviewResult::Deviation, 'Ausnahme bei Altgerät', 'Gerät tauschen', \Illuminate\Support\Carbon::now()->addMonth(), $actor);
        $this->assertSame(1, $measure->reviews()->count());
        $this->assertNotNull($measure->fresh()->next_review_at);
    }

    public function test_approved_vvt_version_freezes_tom_snapshot(): void {
        $org = Organization::factory()->create();
        $actor = User::factory()->create(['organization_id' => $org->id]);
        $vvt = app(ProcessingActivityService::class);
        $tom = app(TechnicalMeasureService::class);

        $activity = $vvt->createDraft($org, 'Lohnabrechnung', 'Entgelt', ControllerRole::Controller, ['data_categories' => 'Stammdaten'], $actor);
        $version = $activity->versions()->first();

        $measure = $tom->createDraft($org, 'Zugriffskonzept', MeasureCategory::DataAccess, ['description' => 'RBAC'], $actor);
        $tom->assignToActivity($measure, $activity);

        $vvt->approve($activity, $version, $actor);

        $snapshot = data_get($version->fresh()->payload, 'tom_snapshot');
        $this->assertIsArray($snapshot);
        $this->assertCount(1, $snapshot);
        $this->assertSame('Zugriffskonzept', $snapshot[0]['name']);
    }

    public function test_assign_only_within_org_via_http(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $measure = app(TechnicalMeasureService::class)->createDraft($org, 'X', MeasureCategory::Management, []);
        $foreignActivity = \App\Models\Privacy\ProcessingActivity::create([
            'organization_id' => Organization::factory()->create()->id, 'name' => 'Fremd', 'controller_role' => 'controller', 'status' => 'draft',
        ]);

        $this->actingAs($officer)->post(route('dataprotection.tom.assign', $measure), ['activity_id' => $foreignActivity->id])
            ->assertNotFound();
    }
}
