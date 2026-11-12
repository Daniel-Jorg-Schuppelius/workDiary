<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ColumnPermissionTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Export\ExportFormat;
use App\Enums\Import\{ImportErrorCode, ImportRunState};
use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExportRun, ImportRun, ImportRunError};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Export\{ExportRunner, ExportSpecRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1625: Verrechnungs- und Kostensätze stehen in Export und Import hinter
 * demselben Recht wie in der Oberfläche — Personen hinter der
 * Personalverwaltung, Projekte hinter der Projektabrechnung.
 */
class ColumnPermissionTransferTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    /** @param list<string> $permissions */
    private function person(array $permissions): User {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function exportedHeader(User $user, \App\Enums\Export\ExportEntity $entity): string {
        $run = app(ExportRunner::class)->run(app(ExportSpecRegistry::class)->for($entity), $this->organization, [], ExportFormat::Csv, $user);

        return strtok((string) Storage::disk('local')->get($run->storage_path), "\r\n") ?: '';
    }

    /**
     * Benutzer und Projekte exportieren heute nur Admins (`user.export` und
     * `project.export` sind keine vergebenen Rechte); die Regel greift im
     * Runner für jede Person und im Download.
     */
    public function test_user_export_omits_rates_without_payroll_permission_and_guards_the_download(): void {
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Erika Beispiel', 'hourly_rate' => '88.00', 'internal_rate' => '41.00']);
        $exporter = $this->person([Permission::UserViewAny->value]);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $header = $this->exportedHeader($exporter, \App\Enums\Export\ExportEntity::Users);
        $this->assertStringContainsString('name', $header);
        $this->assertStringNotContainsString('hourly_rate', $header);
        $this->assertStringNotContainsString('internal_rate', $header);
        $this->assertNull(ExportRun::query()->latest('id')->firstOrFail()->restricted_columns);

        $this->actingAs($admin)->post(route('admin.data.export'), ['entity' => 'users', 'format' => 'csv'])->assertRedirect();
        $full = ExportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(['hourly_rate', 'internal_rate'], $full->restricted_columns);
        $this->assertStringContainsString('88', (string) Storage::disk('local')->get($full->storage_path));
        $this->actingAs($admin)->get(route('admin.data.download', $full))->assertOk();

        // Der Download prüft die enthaltenen Spalten gegen dieselbe Regel.
        $this->assertSame($full->restricted_columns, array_values(array_intersect((array) $full->restricted_columns, \App\Services\Import\ColumnAccess::denied(new \App\Services\Export\Specs\UserExportSpec(), $exporter))));
    }

    public function test_project_export_omits_rates_without_billing_permission(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'hourly_rate' => '95.00']);

        $this->assertStringNotContainsString('hourly_rate', $this->exportedHeader($this->person([Permission::ProjectViewAny->value]), \App\Enums\Export\ExportEntity::Projects));
        $this->assertStringContainsString('hourly_rate', $this->exportedHeader($this->person([Permission::ProjectViewAny->value, Permission::ProjectManageBilling->value]), \App\Enums\Export\ExportEntity::Projects));
    }

    public function test_user_import_skips_rates_without_payroll_permission_and_reports_it(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'erika@example.com', 'name' => 'Erika Alt', 'hourly_rate' => '70.00']);
        $importer = $this->person(['user.import']);
        $csv = "name;email;hourly_rate;internal_rate\nErika Neu;erika@example.com;150,00;99,00\n";

        $this->actingAs($importer)->post(route('admin.imports.preflight'), [
            'entity' => 'users',
            'file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
        ])->assertRedirect();
        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);
        $this->assertEqualsCanonicalizing(
            ['hourly_rate', 'internal_rate'],
            ImportRunError::query()->where('import_run_id', $run->id)->where('code', ImportErrorCode::Blocked->value)->pluck('field')->all(),
        );

        $this->actingAs($importer)->post(route('admin.imports.confirm', $run))->assertRedirect();

        $member->refresh();
        $this->assertSame('Erika Neu', $member->name);
        $this->assertSame('70.00', $member->hourly_rate?->getAmount());
        $this->assertNull($member->internal_rate);
    }

    public function test_user_import_with_payroll_permission_writes_the_rates(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'kim@example.com', 'hourly_rate' => '70.00']);
        $importer = $this->person(['user.import', Permission::UserPayrollManage->value]);

        $this->actingAs($importer)->post(route('admin.imports.preflight'), [
            'entity' => 'users',
            'file' => UploadedFile::fake()->createWithContent('users.csv', "name;email;hourly_rate\nKim Beispiel;kim@example.com;120,00\n"),
        ]);
        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(0, ImportRunError::query()->where('import_run_id', $run->id)->count());
        $this->actingAs($importer)->post(route('admin.imports.confirm', $run))->assertRedirect();

        $this->assertSame('120.00', $member->refresh()->hourly_rate?->getAmount());
    }
}
