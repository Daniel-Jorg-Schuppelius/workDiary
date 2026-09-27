<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StrategicObjectiveTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Investments;

use App\Enums\User\UserRole;
use App\Models\Investments\{InvestmentCase, StrategicObjective};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-942: strategische Ziele mit Kennzahlen und zugeordneten Investitionen. */
final class StrategicObjectiveTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_objective_with_key_results_and_portfolio(): void {
        $this->actingAs($this->admin)->post(route('investments.objectives.store'), [
            'title' => 'Energiekosten senken', 'valid_from' => '2026-01-01', 'valid_until' => '2028-12-31', 'is_active' => 1,
            'key_results' => [
                ['label' => 'Stromverbrauch', 'unit' => 'MWh', 'baseline_value' => '500', 'target_value' => '300', 'current_value' => '400'],
                ['label' => '', 'target_value' => ''],
            ],
        ])->assertRedirect();
        $objective = StrategicObjective::query()->sole();
        $kr = $objective->keyResults()->sole();
        $this->assertSame(50, $kr->progress());

        InvestmentCase::factory()->create(['organization_id' => $this->organization->id, 'title' => 'PV-Anlage', 'strategic_objective_id' => $objective->id]);
        $this->actingAs($this->admin)->get(route('investments.objectives.show', $objective))->assertOk()->assertSee('PV-Anlage')->assertSee('Stromverbrauch')->assertSee('50 %');
        $this->actingAs($this->admin)->get(route('investments.objectives.index'))->assertOk()->assertSee('Energiekosten senken');
    }

    public function test_rights(): void {
        $user = $this->userWithRole(UserRole::User->value);
        $this->actingAs($user)->post(route('investments.objectives.store'), ['title' => 'x'])->assertForbidden();
    }
}
