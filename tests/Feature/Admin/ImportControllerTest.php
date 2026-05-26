<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Import\{ImportEntity, ImportRunState};
use App\Jobs\ProcessCsvImportJob;
use App\Models\{Customer, ImportRun, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ImportControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_non_admin_cannot_open_create_form(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('admin.imports.create', ['entity' => 'customers']))
            ->assertForbidden();
    }

    public function test_admin_sees_index_with_runs(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        ImportRun::create([
            'organization_id' => $this->organization->id,
            'entity' => ImportEntity::Customers,
            'state' => ImportRunState::AwaitingApproval,
            'input_filename' => 'a.csv',
            'input_hash' => str_repeat('a', 64),
            'storage_path' => 'imports/' . $this->organization->id . '/a.csv',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.imports.index'))
            ->assertOk()
            ->assertSee('a.csv');
    }

    public function test_preflight_creates_awaiting_run_for_valid_csv(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $csv = "name;number;currency\nACME;K-1;EUR\nFoo;K-2;EUR\n";
        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.imports.preflight'), [
                'entity' => 'customers',
                'file' => $file,
            ]);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('admin.imports.show', $run));
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);
        $this->assertSame(ImportEntity::Customers, $run->entity);
        $this->assertSame('customers.csv', $run->input_filename);
        $this->assertTrue(Storage::disk('local')->exists($run->storage_path));
    }

    public function test_preflight_marks_failed_when_required_header_missing(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $csv = "number;currency\nK-1;EUR\n";
        $file = UploadedFile::fake()->createWithContent('bad.csv', $csv);

        $this->actingAs($admin)
            ->post(route('admin.imports.preflight'), [
                'entity' => 'customers',
                'file' => $file,
            ])->assertRedirect();

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::Failed, $run->state);
        $this->assertGreaterThan(0, $run->errors()->count());
    }

    public function test_confirm_dispatches_job_only_for_awaiting_state(): void {
        Queue::fake();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $run = ImportRun::create([
            'organization_id' => $this->organization->id,
            'entity' => ImportEntity::Customers,
            'state' => ImportRunState::AwaitingApproval,
            'input_filename' => 'a.csv',
            'input_hash' => str_repeat('a', 64),
            'storage_path' => 'imports/' . $this->organization->id . '/a.csv',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.imports.confirm', $run))
            ->assertRedirect(route('admin.imports.show', $run));

        Queue::assertPushed(ProcessCsvImportJob::class);
    }

    public function test_confirm_blocks_when_state_not_awaiting(): void {
        Queue::fake();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $run = ImportRun::create([
            'organization_id' => $this->organization->id,
            'entity' => ImportEntity::Customers,
            'state' => ImportRunState::Succeeded,
            'input_filename' => 'a.csv',
            'input_hash' => str_repeat('a', 64),
            'storage_path' => 'imports/' . $this->organization->id . '/a.csv',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.imports.confirm', $run))
            ->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_destroy_removes_run_and_file(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $path = 'imports/' . $this->organization->id . '/to-delete.csv';
        Storage::disk('local')->put($path, 'name;number;currency\nA;1;EUR');

        $run = ImportRun::create([
            'organization_id' => $this->organization->id,
            'entity' => ImportEntity::Customers,
            'state' => ImportRunState::AwaitingApproval,
            'input_filename' => 'to-delete.csv',
            'input_hash' => str_repeat('b', 64),
            'storage_path' => $path,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.imports.destroy', $run))
            ->assertRedirect(route('admin.imports.index'));

        $this->assertNull(ImportRun::find($run->id));
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_full_pipeline_creates_customers(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $csv = "name;number;currency\nACME;K-100;EUR\nFoo;K-200;EUR\n";
        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $this->actingAs($admin)
            ->post(route('admin.imports.preflight'), [
                'entity' => 'customers',
                'file' => $file,
            ]);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);

        // Run job synchronously to validate end-to-end behaviour.
        $this->actingAs($admin)
            ->post(route('admin.imports.confirm', $run))
            ->assertRedirect();

        $run->refresh();
        $this->assertContains($run->state, [ImportRunState::Succeeded, ImportRunState::Running]);
        $this->assertSame(2, Customer::query()
            ->where('organization_id', $this->organization->id)
            ->whereIn('number', ['K-100', 'K-200'])
            ->count());
    }
}
