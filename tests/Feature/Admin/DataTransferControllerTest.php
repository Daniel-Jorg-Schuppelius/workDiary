<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Export\ExportRunState;
use App\Models\{Customer, ExportRun, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DataTransferControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_non_admin_cannot_open_export_tab(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('admin.data.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_export_tab(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('admin.data.index'))
            ->assertOk()
            ->assertSee(__('Export erstellen'));
    }

    public function test_export_creates_ready_run_and_downloads_csv(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'number' => 'K-100',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.data.export'), [
                'entity' => 'customers',
                'format' => 'csv',
            ]);

        $run = ExportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ExportRunState::Ready, $run->state);
        $this->assertSame($this->organization->id, $run->organization_id);
        $this->assertSame(1, $run->rows_total);
        $this->assertTrue(Storage::disk('local')->exists($run->storage_path));

        $content = Storage::disk('local')->get($run->storage_path);
        $this->assertStringContainsString('ACME GmbH', (string) $content);
        $this->assertStringContainsString('K-100', (string) $content);

        $this->actingAs($admin)
            ->get(route('admin.data.download', $run))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_download_rejects_run_from_other_organization(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $otherOrg = \App\Models\Organization::factory()->create();
        $other = ExportRun::create([
            'organization_id' => $otherOrg->id,
            'entity' => 'customers',
            'format' => 'csv',
            'state' => ExportRunState::Ready,
            'output_filename' => 'x.csv',
            'storage_path' => 'exports/data/' . $otherOrg->id . '/x.csv',
            'rows_total' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.data.download', $other))
            ->assertNotFound();
    }

    public function test_destroy_removes_file_and_record(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->post(route('admin.data.export'), [
            'entity' => 'customers',
            'format' => 'csv',
        ]);
        $run = ExportRun::query()->latest('id')->firstOrFail();
        $path = $run->storage_path;

        $this->actingAs($admin)
            ->delete(route('admin.data.destroy', $run))
            ->assertRedirect();

        $this->assertModelMissing($run);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }
}
