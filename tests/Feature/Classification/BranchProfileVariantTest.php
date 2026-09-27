<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchProfileVariantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Enums\User\UserRole;
use App\Models\Classification\{BranchProfileVariant, Classification, Tag};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-933: Profilvariante als Überlagerung, installierbar und exportierbar. */
final class BranchProfileVariantTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function variant(): BranchProfileVariant {
        $this->actingAs($this->admin)->post(route('admin.branch-profile-variants.store'), ['base_code' => 'shk', 'code' => 'shk-mueller', 'label' => 'SHK Müller'])->assertRedirect();
        $variant = BranchProfileVariant::query()->sole();
        $this->actingAs($this->admin)->put(route('admin.branch-profile-variants.update', $variant), [
            'label' => 'SHK Müller',
            'removals' => ['tags_seed' => ['#leckage', '#unbekannt'], 'classifications' => ['entry_type/notdienst']],
            'additions' => '{"tags_seed": ["#solar"], "classifications": {"entry_type": [{"code": "solarwartung", "label": "Solarwartung"}]}}',
        ])->assertSessionHas('success');

        return $variant->refresh();
    }

    public function test_variant_overlays_base_profile_and_installs_under_base_code(): void {
        $variant = $this->variant();
        $this->assertSame(['tags_seed' => ['#leckage'], 'classifications' => ['entry_type/notdienst']], $variant->removals);
        $this->assertSame(2, $variant->version);

        $this->actingAs($this->admin)->post(route('admin.branch-profile-variants.install', $variant))->assertSessionHas('success');

        $tags = Tag::query()->pluck('name')->all();
        $this->assertContains('#solar', $tags);
        $this->assertContains('#notdienst', $tags);
        $this->assertNotContains('#leckage', $tags);
        $codes = Classification::query()->where('domain', 'entry_type')->pluck('code')->all();
        $this->assertContains('solarwartung', $codes);
        $this->assertNotContains('notdienst', $codes);

        $settings = (array) $this->organization->refresh()->settings;
        $this->assertArrayHasKey('shk', $settings['branch_profile_versions']);
        $this->assertSame(['code' => 'shk-mueller', 'version' => 2], $settings['branch_profile_variants']['shk']);
        $this->actingAs($this->admin)->get(route('admin.branch-profile-variants.edit', $variant))->assertOk()->assertSee('shk-mueller');
        $this->actingAs($this->admin)->get(route('admin.branch-profiles.index'))->assertOk()->assertSee('SHK Müller');
    }

    public function test_export_is_importable_json_and_invalid_additions_are_rejected(): void {
        $variant = $this->variant();
        $json = (string) $this->actingAs($this->admin)->get(route('admin.branch-profile-variants.export', $variant))->assertOk()->getContent();
        $profile = json_decode($json, true);
        $this->assertSame('shk', $profile['code']);
        $this->assertSame('SHK Müller', $profile['label']);
        $this->assertContains('#solar', $profile['tags_seed']);
        $this->assertNotContains('#leckage', $profile['tags_seed']);

        $this->actingAs($this->admin)->put(route('admin.branch-profile-variants.update', $variant), ['label' => 'x', 'additions' => '["liste"]'])->assertSessionHasErrors('additions');
        $this->actingAs($this->admin)->put(route('admin.branch-profile-variants.update', $variant), ['label' => 'x', 'additions' => '{"classifications": {"erfunden": []}}'])->assertSessionHasErrors('additions');
        $this->actingAs($this->admin)->post(route('admin.branch-profile-variants.store'), ['base_code' => 'gibtsnicht', 'code' => 'x', 'label' => 'X'])->assertSessionHasErrors('base_code');
    }

    public function test_rights(): void {
        $user = $this->userWithRole(UserRole::User->value);
        $this->actingAs($user)->post(route('admin.branch-profile-variants.store'), ['base_code' => 'shk', 'code' => 'x', 'label' => 'X'])->assertForbidden();
    }
}
