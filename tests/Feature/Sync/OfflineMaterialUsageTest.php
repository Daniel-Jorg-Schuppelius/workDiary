<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OfflineMaterialUsageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Enums\Project\ProjectStatus;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\Inventory\StockMovement;
use App\Models\Material\{Material, MaterialUsage};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Materialverbrauch offline (MVP-1594): Befehl `material.usage` von „Heute“ —
 * derselbe Dienst wie online, der offene Stundenzettel des Tages nimmt ihn
 * auf, beim erneuten Senden entsteht keine zweite Zeile.
 */
class OfflineMaterialUsageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string DAY = '2030-01-15';

    private User $user;

    private Project $project;

    private Material $material;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2030-01-16 10:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Außendienst Süd',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
        $this->material = Material::create([
            'organization_id' => $this->organization->id,
            'sku' => 'KAB-3',
            'name' => 'Installationskabel',
            'unit' => 'm',
            'default_unit_price' => 1.5000,
            'tax_rate' => 19.00,
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    private function send(array $payload, ?string $uuid = null): TestResponse {
        return $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => [[
                'client_uuid' => $uuid ?? (string) Str::uuid(),
                'type' => 'material.usage',
                'payload' => $payload,
            ]],
        ]);
    }

    public function test_usage_lands_on_the_open_timesheet_and_is_recorded_once(): void {
        $uuid = (string) Str::uuid();
        $payload = [
            'project_id' => $this->project->sqid,
            'date' => self::DAY,
            'material_id' => $this->material->sqid,
            'description' => 'Installationskabel',
            'quantity' => '12',
            'unit' => 'm',
        ];

        $response = $this->send($payload, $uuid)->assertJsonPath('results.0.status', 'applied');
        $this->send($payload, $uuid)->assertJsonPath('results.0.status', 'duplicate');

        $usage = MaterialUsage::query()->sole();
        $this->assertSame('material_usages:' . $usage->id, $response->json('results.0.ref'));
        $timesheet = Timesheet::query()->sole();
        $this->assertSame((int) $timesheet->id, (int) $usage->timesheet_id);
        $this->assertSame(self::DAY, $timesheet->work_date->toDateString());
        $this->assertSame((int) $this->user->id, (int) $timesheet->user_id);
        // Preis und Steuer aus dem Materialstamm wie im Dialog am Zettel.
        $this->assertSame('18.00', $usage->line_total_net?->getAmount());
        // Der Verbrauch am Stundenzettel ist kein Lagerbeleg — online wie offline.
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_signed_timesheet_gets_a_new_open_one(): void {
        $signed = $this->project->timesheets()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'work_date' => Carbon::parse(self::DAY),
            'status' => TimesheetStatus::Signed->value,
        ]);

        $this->send(['project_id' => $this->project->sqid, 'date' => self::DAY, 'description' => 'Dübel', 'quantity' => '8', 'unit' => 'Stk.'])
            ->assertJsonPath('results.0.status', 'applied');

        $usage = MaterialUsage::query()->sole();
        $this->assertNotSame((int) $signed->id, (int) $usage->timesheet_id);
        $this->assertSame(0, $signed->materialUsages()->count());
    }

    public function test_missing_quantity_becomes_a_conflict(): void {
        $this->send(['project_id' => $this->project->sqid, 'date' => self::DAY, 'description' => 'Dübel', 'unit' => 'Stk.'])
            ->assertJsonPath('results.0.status', 'conflict')
            ->assertJsonPath('results.0.conflict.server.missing', __('validation.attributes.quantity'));

        $this->assertSame(0, MaterialUsage::query()->count());
        $this->assertSame(0, Timesheet::query()->count());
    }

    public function test_without_timesheet_permission_nothing_is_created(): void {
        $this->user->revokePermissionTo(\App\Enums\User\Permission::TimesheetUpdate->value);
        $this->user->syncRoles([]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->send(['project_id' => $this->project->sqid, 'date' => self::DAY, 'description' => 'Dübel', 'quantity' => '8', 'unit' => 'Stk.'])
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.0.errors.command.0', __('sync.error.forbidden'));

        $this->assertSame(0, MaterialUsage::query()->count());
        $this->assertSame(0, Timesheet::query()->count(), 'Die Ablehnung rollt den neu angelegten Zettel zurück.');
    }

    public function test_day_material_form_online_uses_the_same_path(): void {
        $this->actingAs($this->user)
            ->post(route('today.material.store'), [
                'project_id' => $this->project->sqid,
                'date' => self::DAY,
                'material_id' => $this->material->sqid,
                'description' => '',
                'quantity' => '2',
                'unit' => '',
            ])
            ->assertSessionHasErrors(['description', 'unit']);

        $this->actingAs($this->user)
            ->post(route('today.material.store'), [
                'project_id' => $this->project->sqid,
                'date' => self::DAY,
                'material_id' => $this->material->sqid,
                'description' => 'Installationskabel',
                'quantity' => '2',
                'unit' => 'm',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('3.00', MaterialUsage::query()->sole()->line_total_net?->getAmount());
    }

    public function test_day_form_guards_the_timesheet_sum_after_resolving_the_timesheet(): void {
        $this->material->update(['default_unit_price' => 99999.9999]);
        $payload = [
            'project_id' => $this->project->sqid,
            'date' => self::DAY,
            'material_id' => $this->material->sqid,
            'description' => 'Installationskabel',
            'unit' => 'm',
        ];
        $this->actingAs($this->user)->post(route('today.material.store'), $payload + ['quantity' => '99999'])->assertSessionHasNoErrors();

        // Die zweite Zeile sprengte decimal(12,2) des Zettels; der Preis kommt aus dem Materialstamm.
        $this->actingAs($this->user)->post(route('today.material.store'), $payload + ['quantity' => '1000'])
            ->assertSessionHasErrors(['quantity']);
        $this->assertSame(1, MaterialUsage::query()->count());

        // Offline derselbe Weg: abgelehnt, nichts gebucht.
        $this->assertNotSame('applied', $this->send($payload + ['quantity' => '1000'])->json('results.0.status'));
        $this->assertSame(1, MaterialUsage::query()->count());
        $this->assertSame(1, Timesheet::query()->count());
    }
}
