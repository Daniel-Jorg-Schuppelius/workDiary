<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirementAdminControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Classification\ClassificationRequirementPhase;
use App\Enums\Classification\ClassificationRequirementSeverity;
use App\Enums\User\UserRole;
use App\Models\Classification;
use App\Models\ClassificationRequirement;
use App\Models\User;
use Database\Seeders\ClassificationSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ClassificationRequirementAdminControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        $this->seed(ClassificationSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_read_only_user_can_view_index_but_not_create_requirement(): void {
        $user = $this->userWithRole(UserRole::User->value);

        $this->actingAs($user)
            ->get(route('admin.classification-requirements.index'))
            ->assertOk()
            ->assertSee('Pflichtregeln');

        $this->actingAs($user)
            ->post(route('admin.classification-requirements.store'), [
                'entry_type_code' => 'service',
                'required_domain' => ClassificationDomain::DefectType->value,
                'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
            ])
            ->assertForbidden();
    }

    public function test_teamleitung_can_create_requirement(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        Classification::factory()->forOrganization($this->organization->id)->domain(ClassificationDomain::EntryType)->create([
            'code' => 'service',
            'label' => 'Service lokal',
        ]);

        $this->actingAs($user)
            ->post(route('admin.classification-requirements.store'), [
                'entry_type_code' => 'service',
                'required_domain' => ClassificationDomain::DefectType->value,
                'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'allow_multi' => '1',
                'min_count' => 1,
                'max_count' => 2,
                'only_if_json' => '{"priority":["high","critical"]}',
                'note' => 'Nur bei hoher Priorität',
            ])
            ->assertRedirect(route('admin.classification-requirements.index'));

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->organization->id,
            'entry_type_code' => 'service',
            'required_domain' => ClassificationDomain::DefectType->value,
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'allow_multi' => true,
            'min_count' => 1,
            'max_count' => 2,
            'note' => 'Nur bei hoher Priorität',
        ]);
    }

    public function test_teamleitung_can_update_requirement(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $requirement = ClassificationRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'entry_type_code' => 'service',
            'required_domain' => ClassificationDomain::DefectType->value,
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
        ]);

        $this->actingAs($user)
            ->put(route('admin.classification-requirements.update', $requirement), [
                'entry_type_code' => 'service',
                'required_domain' => ClassificationDomain::RootCause->value,
                'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
                'severity' => ClassificationRequirementSeverity::Soft->value,
                'min_count' => 2,
                'max_count' => 3,
                'note' => 'Bei Abschluss prüfen',
                'only_if_json' => '',
            ])
            ->assertRedirect(route('admin.classification-requirements.index'));

        $this->assertDatabaseHas('classification_requirements', [
            'id' => $requirement->id,
            'required_domain' => ClassificationDomain::RootCause->value,
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'min_count' => 2,
            'max_count' => 3,
            'note' => 'Bei Abschluss prüfen',
        ]);
    }

    public function test_invalid_only_if_json_returns_validation_error(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $this->actingAs($user)
            ->from(route('admin.classification-requirements.create'))
            ->post(route('admin.classification-requirements.store'), [
                'entry_type_code' => 'service',
                'required_domain' => ClassificationDomain::DefectType->value,
                'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'only_if_json' => '{invalid}',
            ])
            ->assertRedirect(route('admin.classification-requirements.create'))
            ->assertSessionHasErrors('only_if_json');
    }

    public function test_teamleitung_can_delete_requirement(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $requirement = ClassificationRequirement::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($user)
            ->delete(route('admin.classification-requirements.destroy', $requirement))
            ->assertRedirect(route('admin.classification-requirements.index'));

        $this->assertDatabaseMissing('classification_requirements', ['id' => $requirement->id]);
    }

    public function test_index_can_filter_requirements_by_search_query(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        ClassificationRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'entry_type_code' => 'service',
            'required_domain' => ClassificationDomain::DefectType->value,
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
        ]);
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'entry_type_code' => 'maintenance',
            'required_domain' => ClassificationDomain::Result->value,
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
        ]);

        $this->actingAs($user)
            ->get(route('admin.classification-requirements.index', ['q' => 'service']))
            ->assertOk()
            ->assertSee('service')
            ->assertDontSee('maintenance');
    }

    public function test_index_can_filter_requirements_by_phase_and_severity(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        ClassificationRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'entry_type_code' => 'service',
            'required_domain' => ClassificationDomain::DefectType->value,
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
        ]);
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'entry_type_code' => 'service',
            'required_domain' => ClassificationDomain::RootCause->value,
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
        ]);

        $this->actingAs($user)
            ->get(route('admin.classification-requirements.index', [
                'phase' => ClassificationRequirementPhase::BeforeComplete->value,
                'severity' => ClassificationRequirementSeverity::Soft->value,
            ]))
            ->assertOk()
            ->assertSee('Ursachen')
            ->assertDontSee('Fehlertypen');
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
