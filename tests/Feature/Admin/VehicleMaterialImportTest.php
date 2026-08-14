<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleMaterialImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Import\{ImportEntity, ImportErrorCode, ImportRunState};
use App\Models\{ImportRun, Material, User, Vehicle};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class VehicleMaterialImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_create_form_lists_vehicle_and_material_entities(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('admin.imports.create', ['entity' => 'vehicles']))
            ->assertOk()
            ->assertSee(__('import.entity.vehicles'))
            ->assertSee(__('import.entity.materials'));
    }

    public function test_non_admin_cannot_open_vehicle_import(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('admin.imports.create', ['entity' => 'vehicles']))
            ->assertForbidden();
    }

    public function test_vehicle_full_pipeline_creates_vehicles(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $csv = "license_plate;label;vehicle_type;propulsion;ownership\n"
            . "B-AA 11;Sprinter;van;diesel;owned\n"
            . "B-BB 22;eGolf;car;electric;leased\n";
        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csv);

        $this->actingAs($admin)->post(route('admin.imports.preflight'), [
            'entity' => 'vehicles',
            'file' => $file,
        ]);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);
        $this->assertSame(ImportEntity::Vehicles, $run->entity);

        $this->actingAs($admin)->post(route('admin.imports.confirm', $run))->assertRedirect();
        $run->refresh();
        $this->assertContains($run->state, [ImportRunState::Succeeded, ImportRunState::Running]);

        $this->assertSame(2, Vehicle::query()
            ->where('organization_id', $this->organization->id)
            ->whereIn('license_plate', ['B-AA 11', 'B-BB 22'])
            ->count());
    }

    public function test_vehicle_preflight_flags_missing_plate_and_invalid_enum(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $csv = "license_plate;vehicle_type\n"
            . ";van\n"
            . "B-OK 1;spaceship\n";
        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csv);

        $this->actingAs($admin)->post(route('admin.imports.preflight'), [
            'entity' => 'vehicles',
            'file' => $file,
        ]);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $codes = $run->errors()->pluck('code')->all();

        $this->assertContains(ImportErrorCode::Required, $codes);
        $this->assertContains(ImportErrorCode::Format, $codes);
    }

    public function test_vehicle_import_is_idempotent_by_license_plate(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
            'license_plate' => 'B-DUP 9',
            'label' => 'Old',
        ]);

        $csv = "license_plate;label\nB-DUP 9;New Label\n";
        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csv);

        $this->actingAs($admin)->post(route('admin.imports.preflight'), [
            'entity' => 'vehicles',
            'file' => $file,
        ]);
        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.imports.confirm', $run));

        $this->assertSame(1, Vehicle::query()
            ->where('organization_id', $this->organization->id)
            ->where('license_plate', 'B-DUP 9')
            ->count());
        $this->assertSame('New Label', Vehicle::query()->where('license_plate', 'B-DUP 9')->value('label'));
    }

    public function test_material_full_pipeline_creates_materials(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $csv = "sku;name;unit;default_unit_price\n"
            . "M-1;Schrauben;Stk;0,05\n"
            . "M-2;Kabel;m;1,20\n";
        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $this->actingAs($admin)->post(route('admin.imports.preflight'), [
            'entity' => 'materials',
            'file' => $file,
        ]);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);

        $this->actingAs($admin)->post(route('admin.imports.confirm', $run))->assertRedirect();
        $run->refresh();
        $this->assertContains($run->state, [ImportRunState::Succeeded, ImportRunState::Running]);

        $this->assertSame(2, Material::query()
            ->where('organization_id', $this->organization->id)
            ->whereIn('sku', ['M-1', 'M-2'])
            ->count());
    }

    public function test_material_preflight_flags_missing_required_columns(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        // Missing required header "unit".
        $csv = "sku;name\nM-9;Nur Name\n";
        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $this->actingAs($admin)->post(route('admin.imports.preflight'), [
            'entity' => 'materials',
            'file' => $file,
        ]);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::Failed, $run->state);
        $this->assertGreaterThan(0, $run->errors()->count());
    }
}
