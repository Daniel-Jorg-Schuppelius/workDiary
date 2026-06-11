<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeRuleAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Surcharge;

use App\Models\{Organization, User};
use App\Models\Surcharge\SurchargeRule;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 005 — Admin-CRUD und Permissions der Zuschlagsregeln.
 */
class SurchargeRuleAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
    }

    public function test_regular_user_cannot_access_rules_index(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('admin.surcharge-rules.index'))
            ->assertForbidden();
    }

    public function test_regular_user_cannot_create_rule(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('admin.surcharge-rules.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_buchhaltung_can_create_update_and_delete_rule(): void {
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($accountant)
            ->get(route('admin.surcharge-rules.index'))
            ->assertOk();

        $this->actingAs($accountant)
            ->post(route('admin.surcharge-rules.store'), $this->payload())
            ->assertRedirect(route('admin.surcharge-rules.index'));

        $rule = SurchargeRule::query()->where('code', 'night')->firstOrFail();
        $this->assertSame((int) $this->organization->id, (int) $rule->organization_id);
        $this->assertSame('25.00', (string) $rule->percentage);
        $this->assertSame('2010', $rule->wage_type_code);

        $this->actingAs($accountant)
            ->put(route('admin.surcharge-rules.update', $rule), $this->payload([
                'percentage' => '40',
                'label' => 'Nachtzuschlag neu',
            ]))
            ->assertRedirect(route('admin.surcharge-rules.index'));

        $rule->refresh();
        $this->assertSame('40.00', (string) $rule->percentage);
        $this->assertSame('Nachtzuschlag neu', $rule->label);

        $this->actingAs($accountant)
            ->delete(route('admin.surcharge-rules.destroy', $rule))
            ->assertRedirect(route('admin.surcharge-rules.index'));

        $this->assertSoftDeleted('surcharge_rules', ['id' => $rule->id]);
    }

    public function test_admin_can_manage_rules(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->post(route('admin.surcharge-rules.store'), $this->payload(['code' => 'sunday', 'kind' => 'sunday', 'window_start' => null, 'window_end' => null]))
            ->assertRedirect(route('admin.surcharge-rules.index'));

        $rule = SurchargeRule::query()->where('code', 'sunday')->firstOrFail();
        // Ganztags-Arten verwerfen das Zeitfenster.
        $this->assertNull($rule->window_start);
        $this->assertNull($rule->window_end);
    }

    public function test_window_is_required_for_night_kind(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->post(route('admin.surcharge-rules.store'), $this->payload(['window_start' => null, 'window_end' => null]))
            ->assertSessionHasErrors(['window_start', 'window_end']);
    }

    public function test_code_must_be_unique_per_organization(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        SurchargeRule::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'night',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.surcharge-rules.store'), $this->payload())
            ->assertSessionHasErrors(['code']);
    }

    public function test_rule_of_other_organization_is_not_editable(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $orgB = Organization::factory()->create();
        $foreign = SurchargeRule::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($admin)
            ->get(route('admin.surcharge-rules.edit', ['surchargeRule' => $foreign->sqid]))
            ->assertNotFound();
    }

    /** @param  array<string, mixed>  $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array {
        return array_merge([
            'code' => 'night',
            'label' => 'Nachtzuschlag',
            'kind' => 'night',
            'window_start' => '23:00',
            'window_end' => '06:00',
            'percentage' => '25',
            'wage_type_code' => '2010',
            'priority' => '0',
            'active' => '1',
            'valid_from' => null,
            'valid_until' => null,
        ], $overrides);
    }
}
