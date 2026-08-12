<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeDimensionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\User\Permission;
use App\Models\{Project, TimeAllocation, TimeDimensionType, TimeDimensionValue, TimeEntry, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-514 P2 (Feature 103): freie Mandanten-Dimensionen — Admin-Pflege,
 * Gültigkeit im Aufteilen-Dialog, Aufteilung + Report-Gruppierung.
 */
class TimeDimensionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function type(string $name = 'ERP-Auftrag', bool $enabled = true): TimeDimensionType {
        return TimeDimensionType::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'erp-' . fake()->unique()->numberBetween(1, 9999),
            'name' => $name,
            'enabled' => $enabled,
        ]);
    }

    public function test_admin_can_manage_types_and_values(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.time-dimensions.types.store'), ['code' => 'erp-auftrag', 'name' => 'ERP-Auftrag'])
            ->assertRedirect();
        $type = TimeDimensionType::query()->where('code', 'erp-auftrag')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.time-dimensions.values.store', $type), [
                'name' => 'Auftrag 4711',
                'external_id' => 'ERP-4711',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('time_dimension_values', [
            'dimension_type_id' => $type->id,
            'name' => 'Auftrag 4711',
            'external_id' => 'ERP-4711',
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.time-dimensions.types.toggle', $type))
            ->assertRedirect();
        $this->assertFalse((bool) $type->fresh()->enabled);

        $plain = $this->orgUser();
        $this->actingAs($plain)->get(route('admin.time-dimensions.index'))->assertForbidden();
    }

    public function test_dialog_offers_only_valid_values_of_enabled_types(): void {
        $enabled = $this->type('Sichtbar');
        $enabled->values()->create(['organization_id' => $this->organization->id, 'name' => 'Gültig']);
        $enabled->values()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Abgelaufen',
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $disabled = $this->type('Unsichtbar', false);
        $disabled->values()->create(['organization_id' => $this->organization->id, 'name' => 'Versteckt']);

        $user = $this->orgUser();
        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => Project::factory()->create(['organization_id' => $this->organization->id])->id,
            'minutes' => 480,
            'date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('time-entries.allocations.edit', $entry) . '?dialog=1')
            ->assertOk();

        $response->assertSee('Sichtbar');
        $response->assertSee('Gültig');
        $response->assertDontSee('Abgelaufen');
        $response->assertDontSee('Versteckt');
    }

    public function test_allocation_and_report_group_custom_dimension_per_type(): void {
        $type = $this->type('ERP-Auftrag');
        $value = $type->values()->create(['organization_id' => $this->organization->id, 'name' => 'Auftrag 4711']);

        // Aktuelles Datum: das Korrekturfenster (7 Tage) gilt auch für Aufteilungen des Eigentümers.
        $user = $this->orgUser();
        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => Project::factory()->create(['organization_id' => $this->organization->id])->id,
            'minutes' => 480,
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->put(route('time-entries.allocations.update', $entry), [
                'allocations' => [
                    ['target' => 'dimension:' . Sqid::encode(TimeDimensionValue::class, (int) $value->id), 'minutes' => 90],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('time_allocations', [
            'time_entry_id' => $entry->id,
            'allocatable_type' => TimeDimensionValue::class,
            'allocatable_id' => $value->id,
            'duration_minutes' => 90,
        ]);

        $viewer = $this->orgUser();
        $viewer->givePermissionTo(Permission::ReportView->value);
        $response = $this->actingAs($viewer)
            ->get(route('reports.allocations', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]))
            ->assertOk();

        $groups = $response->viewData('groups');
        $key = 'dimension:' . $type->id;
        $this->assertArrayHasKey($key, $groups);
        $this->assertSame('ERP-Auftrag', $groups[$key]['label']);
        $this->assertSame('Auftrag 4711', $groups[$key]['rows'][0]['name']);
        $this->assertSame(90, $groups[$key]['rows'][0]['minutes']);
    }

    public function test_allocation_count_shows_in_alias(): void {
        $type = $this->type();
        $value = $type->values()->create(['organization_id' => $this->organization->id, 'name' => 'X']);

        $allocation = new TimeAllocation(['allocatable_type' => TimeDimensionValue::class]);
        $this->assertSame('dimension', $allocation->typeAlias());
        $this->assertTrue($value->isValidOn(now()));
    }
}
