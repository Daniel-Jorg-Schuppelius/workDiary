<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAllocationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{CostCenter, Organization, Project, TimeAllocation, TimeEntry, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-514 (Feature 103): Zeitaufteilung — Dialog, Summen-Validierung,
 * Org-Grenzen, Sperren und idempotentes Ersetzen.
 */
class TimeAllocationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    private TimeEntry $entry;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgUser();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'minutes' => 480,
            'date' => now()->toDateString(),
        ]);
    }

    private function target(string $alias, int $id): string {
        return $alias . ':' . Sqid::encode(TimeAllocation::TYPES[$alias], $id);
    }

    public function test_dialog_renders_for_owner(): void {
        // Regression Browser-Smoke 2026-08-12: Optionslisten müssen auch mit
        // BEFÜLLTEN Dimensionen rendern (Vehicle/ActivityCategory haben label statt name).
        \App\Models\Vehicle::factory()->create(['organization_id' => $this->organization->id, 'label' => 'Transporter']);
        \App\Models\ActivityCategory::create([
            'organization_id' => $this->organization->id,
            'key' => 'montage',
            'label' => 'Montage',
            'activity_type' => 'internal',
            'active' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('time-entries.allocations.edit', $this->entry) . '?dialog=1')
            ->assertOk()
            ->assertSee(__('allocation.title'))
            ->assertSee('Transporter');
    }

    public function test_owner_can_save_and_replace_allocations(): void {
        $costCenter = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'K100',
            'label' => 'Technik',
            'active' => true,
        ]);

        $this->actingAs($this->user)
            ->put(route('time-entries.allocations.update', $this->entry), [
                'allocations' => [
                    ['target' => $this->target('project', (int) $this->project->id), 'minutes' => 300],
                    ['target' => $this->target('cost_center', (int) $costCenter->id), 'minutes' => 120, 'comment' => 'Wartung'],
                    ['target' => '', 'minutes' => ''],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, TimeAllocation::query()->where('time_entry_id', $this->entry->id)->count());
        $this->assertDatabaseHas('time_allocations', [
            'time_entry_id' => $this->entry->id,
            'allocatable_type' => CostCenter::class,
            'allocatable_id' => $costCenter->id,
            'duration_minutes' => 120,
            'comment' => 'Wartung',
            'organization_id' => $this->organization->id,
        ]);

        // Erneutes Speichern ersetzt statt zu ergänzen; leere Liste räumt auf.
        $this->actingAs($this->user)
            ->put(route('time-entries.allocations.update', $this->entry), [
                'allocations' => [
                    ['target' => $this->target('project', (int) $this->project->id), 'minutes' => 60],
                ],
            ])
            ->assertRedirect();
        $this->assertSame(1, TimeAllocation::query()->where('time_entry_id', $this->entry->id)->count());

        $this->actingAs($this->user)
            ->put(route('time-entries.allocations.update', $this->entry), ['allocations' => []])
            ->assertRedirect();
        $this->assertSame(0, TimeAllocation::query()->where('time_entry_id', $this->entry->id)->count());
    }

    public function test_sum_exceeding_entry_duration_is_rejected(): void {
        $this->actingAs($this->user)
            ->put(route('time-entries.allocations.update', $this->entry), [
                'allocations' => [
                    ['target' => $this->target('project', (int) $this->project->id), 'minutes' => 481],
                ],
            ])
            ->assertSessionHasErrors('allocations');

        $this->assertSame(0, TimeAllocation::query()->count());
    }

    public function test_cross_tenant_target_is_rejected(): void {
        $foreignOrg = Organization::factory()->create();
        $foreignProject = Project::factory()->create(['organization_id' => $foreignOrg->id]);

        $this->actingAs($this->user)
            ->put(route('time-entries.allocations.update', $this->entry), [
                'allocations' => [
                    ['target' => $this->target('project', (int) $foreignProject->id), 'minutes' => 60],
                ],
            ])
            ->assertSessionHasErrors('allocations');

        $this->assertSame(0, TimeAllocation::query()->withoutGlobalScopes()->count());
    }

    public function test_foreign_user_cannot_split(): void {
        $other = $this->orgUser();

        $this->actingAs($other)
            ->put(route('time-entries.allocations.update', $this->entry), [
                'allocations' => [
                    ['target' => $this->target('project', (int) $this->project->id), 'minutes' => 60],
                ],
            ])
            ->assertForbidden();
    }

    public function test_exported_entry_is_hard_locked(): void {
        $this->entry->forceFill(['exported' => true])->save();
        $admin = $this->orgAdmin();

        $this->actingAs($admin)
            ->put(route('time-entries.allocations.update', $this->entry), [
                'allocations' => [
                    ['target' => $this->target('project', (int) $this->project->id), 'minutes' => 60],
                ],
            ])
            ->assertSessionHasErrors('allocations');

        $this->assertSame(0, TimeAllocation::query()->count());
    }
}
