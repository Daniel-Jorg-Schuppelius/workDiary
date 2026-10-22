<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationPermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\User\{Permission, UserRole};
use App\Models\Classification\{ActivityCategory, EntryType, Tag};
use App\Models\Material\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Stammlisten-Pflege (lk-06): Tätigkeitskategorien, Auftragsarten, Tags und
 * Materialien pflegt, wer das jeweilige Verwaltungsrecht hat; vergeben ist es
 * nur an Admins, delegierbar über den Rollen-Editor.
 */
class ClassificationPermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_master_lists_follow_their_manage_rights(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $admin = $this->orgAdmin();
        $tag = Tag::factory()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);

        $cases = [
            [Permission::ActivityCategoryManage, 'create', ActivityCategory::class],
            [Permission::EntryTypeManage, 'viewAny', EntryType::class],
            [Permission::EntryTypeManage, 'create', EntryType::class],
            [Permission::MaterialManage, 'create', Material::class],
            [Permission::TagManage, 'update', $tag],
        ];
        foreach ($cases as [$permission, $ability, $subject]) {
            $holder = $this->actorIn($this->organization, [$permission]);
            $this->assertTrue(Gate::forUser($holder)->allows($ability, $subject), $permission->value);
            $this->assertFalse(Gate::forUser($lead)->allows($ability, $subject), $permission->value);
            $this->assertTrue(Gate::forUser($admin)->allows($ability, $subject), $permission->value);
        }

        $this->assertTrue(Gate::forUser($lead)->allows('create', Tag::class), 'Tags legt jeder ad hoc an');
    }

    public function test_holidays_follow_holiday_manage(): void {
        $this->actingAs($this->actorIn($this->organization, [Permission::HolidayManage]))->get(route('holidays.index'))->assertOk();
        $this->actingAs($this->userWithRole(UserRole::Buchhaltung->value))->get(route('holidays.index'))->assertForbidden();
        $this->actingAs($this->orgAdmin())->get(route('holidays.index'))->assertOk();
    }

    public function test_branding_follows_branding_update(): void {
        $this->assertTrue(Gate::forUser($this->actorIn($this->organization, [Permission::BrandingUpdate]))->allows('manageBranding', $this->organization));
        $this->assertFalse(Gate::forUser($this->userWithRole(UserRole::Geschaeftsfuehrung->value))->allows('manageBranding', $this->organization));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('manageBranding', $this->organization));
    }
}
