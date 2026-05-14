<?php

namespace Tests\Feature;

use App\Mail\TimesheetSignedMail;
use App\Models\Material;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TimesheetTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'TS-Projekt',
            'status' => Project::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_user_can_create_timesheet(): void
    {
        $this->actingAs($this->user)
            ->post(route('projects.timesheets.store', $this->project), [
                'work_date' => '2030-02-01',
                'customer_name' => 'Kunde GmbH',
                'customer_email' => 'kunde@example.com',
                'notes' => 'Erste Anfahrt',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('timesheets', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => '2030-02-01 00:00:00',
            'status' => Timesheet::STATUS_DRAFT,
            'customer_name' => 'Kunde GmbH',
        ]);
    }

    public function test_entry_recalculates_totals(): void
    {
        $ts = $this->makeTimesheet();
        $ts->entries()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => $ts->work_date,
            'minutes' => 90,
            'kind' => TimeEntry::KIND_WORK,
        ]);

        $this->assertSame(90, (int) $ts->fresh()->totals_minutes);
    }

    public function test_material_usage_computes_line_total(): void
    {
        $ts = $this->makeTimesheet();
        $material = Material::create([
            'organization_id' => $this->organization->id,
            'sku' => 'TST-1',
            'name' => 'Testartikel',
            'unit' => 'Stk',
            'default_unit_price' => 5.0000,
            'tax_rate' => 19.00,
            'is_active' => true,
        ]);
        $usage = $ts->materialUsages()->create([
            'material_id' => $material->id,
            'description' => $material->name,
            'quantity' => 3,
            'unit' => $material->unit,
            'unit_price' => $material->default_unit_price,
            'tax_rate' => $material->tax_rate,
        ]);

        $this->assertSame('15.00', (string) $usage->fresh()->line_total_net);
        $this->assertSame('15.00', (string) $ts->fresh()->totals_material_net);
    }

    public function test_signature_locks_editing_and_dispatches_mail(): void
    {
        Storage::fake('local');
        Mail::fake();

        $ts = $this->makeTimesheet(['customer_email' => 'kunde@example.com']);
        $png = $this->fakePngBase64();

        $this->actingAs($this->user)
            ->post(route('projects.timesheets.sign', [$this->project, $ts]), [
                'signature' => $png,
                'customer_name' => 'Max Kunde',
            ])
            ->assertRedirect();

        $ts->refresh();
        $this->assertSame(Timesheet::STATUS_SIGNED, $ts->status);
        $this->assertNotNull($ts->signed_at);
        $this->assertNotNull($ts->signature_attachment_id);
        $this->assertFalse($ts->canEdit());

        Mail::assertSent(TimesheetSignedMail::class);
    }

    public function test_signed_timesheet_blocks_entry_changes(): void
    {
        $ts = $this->makeTimesheet(['status' => Timesheet::STATUS_SIGNED]);

        $this->actingAs($this->user)
            ->post(route('projects.timesheets.entries.store', [$this->project, $ts]), [
                'date' => $ts->work_date->toDateString(),
                'minutes' => 60,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_lock_and_unlock_timesheet(): void
    {
        $ts = $this->makeTimesheet(['status' => Timesheet::STATUS_SIGNED]);

        $this->actingAs($this->admin)
            ->post(route('projects.timesheets.lock', [$this->project, $ts]))
            ->assertRedirect();

        $this->assertSame(Timesheet::STATUS_LOCKED, $ts->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('projects.timesheets.unlock', [$this->project, $ts]))
            ->assertRedirect();

        $this->assertSame(Timesheet::STATUS_SIGNED, $ts->fresh()->status);
    }

    public function test_public_signature_via_magic_token(): void
    {
        Storage::fake('local');
        $ts = $this->makeTimesheet();
        $ts->forceFill(['magic_token' => 'tok123', 'magic_expires_at' => now()->addDay()])->save();

        $this->get(route('timesheets.public-sign', 'tok123'))->assertOk();

        $this->post(route('timesheets.public-sign.submit', 'tok123'), [
            'signature' => $this->fakePngBase64(),
            'customer_name' => 'Extern Kunde',
        ])->assertRedirect(route('timesheets.public-thanks'));

        $ts->refresh();
        $this->assertSame(Timesheet::STATUS_SIGNED, $ts->status);
        $this->assertNull($ts->magic_token);
    }

    public function test_expired_magic_token_is_rejected(): void
    {
        $ts = $this->makeTimesheet();
        $ts->forceFill(['magic_token' => 'old', 'magic_expires_at' => now()->subDay()])->save();

        $this->get(route('timesheets.public-sign', 'old'))->assertStatus(410);
    }

    private function makeTimesheet(array $attrs = []): Timesheet
    {
        return Timesheet::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => '2030-02-15',
            'status' => Timesheet::STATUS_DRAFT,
        ], $attrs));
    }

    private function fakePngBase64(): string
    {
        // 1x1 transparent PNG
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }
}
