<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationAdminControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\User\UserRole;
use App\Models\Classification;
use App\Models\User;
use App\Services\Classification\ClassificationManager;
use Database\Seeders\ClassificationSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ClassificationAdminControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        $this->seed(ClassificationSeeder::class);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_read_only_user_can_view_index_but_not_store(): void {
        $user = $this->userWithRole(UserRole::User->value);

        $this->actingAs($user)
            ->get(route('admin.classifications.index'))
            ->assertOk()
            ->assertSee('Klassifikationen')
            ->assertSee('Service');

        $this->actingAs($user)
            ->post(route('admin.classifications.store'), [
                'domain' => ClassificationDomain::Activity->value,
                'code' => 'custom_activity',
                'label' => 'Eigene Tätigkeit',
                'active' => '1',
            ])
            ->assertForbidden();
    }

    public function test_teamleitung_can_create_org_classification(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $this->actingAs($user)
            ->post(route('admin.classifications.store'), [
                'domain' => ClassificationDomain::Activity->value,
                'code' => 'custom_activity',
                'label' => 'Eigene Tätigkeit',
                'sort_order' => 70,
                'color_hex' => '#0055AA',
                'icon' => 'build',
                'description' => 'Nur für diese Organisation',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.classifications.index'));

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::Activity->value,
            'code' => 'custom_activity',
            'label' => 'Eigene Tätigkeit',
            'sort_order' => 70,
        ]);
    }

    public function test_teamleitung_can_override_platform_default(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $platform = Classification::query()
            ->whereNull('organization_id')
            ->where('domain', ClassificationDomain::EntryType->value)
            ->where('code', 'service')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.classifications.store'), [
                'source_classification_id' => $platform->id,
                'label' => 'Service (Org)',
                'sort_order' => 15,
                'active' => '1',
            ])
            ->assertRedirect(route('admin.classifications.index'));

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::EntryType->value,
            'code' => 'service',
            'label' => 'Service (Org)',
            'sort_order' => 15,
            'active' => true,
        ]);
    }

    public function test_teamleitung_can_update_org_classification(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $classification = Classification::factory()
            ->forOrganization($this->organization->id)
            ->domain(ClassificationDomain::Result)
            ->create([
                'code' => 'custom_result',
                'label' => 'Alt',
            ]);

        $this->actingAs($user)
            ->put(route('admin.classifications.update', $classification), [
                'label' => 'Neu',
                'sort_order' => 90,
                'color_hex' => '#112233',
                'icon' => 'check_circle',
                'description' => 'Aktualisiert',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.classifications.index'));

        $this->assertDatabaseHas('classifications', [
            'id' => $classification->id,
            'label' => 'Neu',
            'sort_order' => 90,
            'color_hex' => '#112233',
        ]);
    }

    public function test_teamleitung_can_delete_unused_org_classification(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $classification = Classification::factory()
            ->forOrganization($this->organization->id)
            ->domain(ClassificationDomain::Priority)
            ->create(['code' => 'custom_priority']);

        $this->actingAs($user)
            ->delete(route('admin.classifications.destroy', $classification))
            ->assertRedirect(route('admin.classifications.index'));

        $this->assertDatabaseMissing('classifications', ['id' => $classification->id]);
    }

    public function test_referenced_org_classification_returns_error_flash(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $classification = Classification::factory()
            ->forOrganization($this->organization->id)
            ->domain(ClassificationDomain::Activity)
            ->create(['code' => 'referenced_activity']);

        Schema::create('classification_refs', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('classification_id');
        });

        DB::table('classification_refs')->insert(['classification_id' => $classification->id]);
        app(ClassificationManager::class)->registerReference(ClassificationDomain::Activity, 'classification_refs', 'classification_id');

        try {
            $this->actingAs($user)
                ->delete(route('admin.classifications.destroy', $classification))
                ->assertRedirect(route('admin.classifications.index'))
                ->assertSessionHas('error');
        } finally {
            Schema::dropIfExists('classification_refs');
        }

        $this->assertDatabaseHas('classifications', ['id' => $classification->id]);
    }

    public function test_teamleitung_can_deactivate_platform_default_for_organization(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $platform = Classification::query()
            ->whereNull('organization_id')
            ->where('domain', ClassificationDomain::Result->value)
            ->where('code', 'escalated')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.classifications.deactivate-default', $platform))
            ->assertRedirect(route('admin.classifications.index'));

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::Result->value,
            'code' => 'escalated',
            'active' => false,
        ]);
    }

    public function test_teamleitung_can_reorder_org_classifications_with_domain_form(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $first = Classification::factory()
            ->forOrganization($this->organization->id)
            ->domain(ClassificationDomain::Activity)
            ->create(['code' => 'first_activity', 'sort_order' => 300]);
        $second = Classification::factory()
            ->forOrganization($this->organization->id)
            ->domain(ClassificationDomain::Activity)
            ->create(['code' => 'second_activity', 'sort_order' => 100]);

        $this->actingAs($user)
            ->post(route('admin.classifications.reorder', ClassificationDomain::Activity->value), [
                'sort_map' => [
                    $first->id => 20,
                    $second->id => 10,
                ],
            ])
            ->assertRedirect(route('admin.classifications.index'));

        $refreshedFirst = $first->fresh();
        $refreshedSecond = $second->fresh();

        $this->assertInstanceOf(Classification::class, $refreshedFirst);
        $this->assertInstanceOf(Classification::class, $refreshedSecond);
        $this->assertSame(20, $refreshedFirst->sort_order);
        $this->assertSame(10, $refreshedSecond->sort_order);
    }

    public function test_teamleitung_can_import_classifications_from_csv(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        Classification::factory()
            ->forOrganization($this->organization->id)
            ->domain(ClassificationDomain::Result)
            ->create([
                'code' => 'custom_result',
                'label' => 'Alt',
                'sort_order' => 40,
            ]);

        $file = UploadedFile::fake()->createWithContent('classifications.csv', implode("\n", [
            'domain;code;label;sort_order;color_hex;icon',
            'activity;analysis;Analyse Plus;150;#334455;analytics',
            'result;custom_result;Neues Ergebnis;120;#112233;check_circle',
        ]));

        $this->actingAs($user)
            ->post(route('admin.classifications.import'), ['file' => $file])
            ->assertRedirect(route('admin.classifications.index'));

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::Activity->value,
            'code' => 'analysis',
            'label' => 'Analyse Plus',
            'sort_order' => 150,
            'color_hex' => '#334455',
            'icon' => 'analytics',
        ]);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::Result->value,
            'code' => 'custom_result',
            'label' => 'Neues Ergebnis',
            'sort_order' => 120,
            'color_hex' => '#112233',
            'icon' => 'check_circle',
        ]);
    }

    private function userWithRole(string $role): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);

        $orgRole = Role::query()
            ->where('name', $role)
            ->where('team_id', $this->organization->id)
            ->firstOrFail();

        $user->syncRoles([$orgRole]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user;
    }
}
