<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiBudgetSettingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ai;

use App\Enums\Ai\AiFamily;
use App\Models\Platform\User;
use App\Services\Ai\AiBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Monatsbudget je Familie (Feature 025, MVP-399/1620): Schreibweg über den
 * Dialog der Verbrauchsseite, Wirkung über den AiBudgetService.
 */
class AiBudgetSettingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_admin_sets_and_resets_the_monthly_budget(): void {
        config(['ai.budget.monthly_units.translation' => 5000]);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->get(route('admin.ai.usage'))->assertOk()
            ->assertSee(route('admin.ai.budget.edit'), false);
        $this->actingAs($admin)->get(route('admin.ai.budget.edit'))->assertOk()
            ->assertSee('name="monthly_units[llm]"', false)
            ->assertSee('name="monthly_units[translation]"', false);

        $this->actingAs($admin)->put(route('admin.ai.budget.update'), ['monthly_units' => ['llm' => '250000', 'translation' => '']])
            ->assertRedirect(route('admin.ai.usage'))->assertSessionHas('success');

        $organization = $this->organization->fresh();
        $budget = app(AiBudgetService::class);
        $this->assertSame(250000, $budget->limitFor($organization, AiFamily::Llm));
        $this->assertSame(5000, $budget->limitFor($organization, AiFamily::Translation), 'Leer gilt die Vorgabe des Betreibers.');

        $this->actingAs($admin)->put(route('admin.ai.budget.update'), ['monthly_units' => ['llm' => '', 'translation' => '']])->assertRedirect();
        $this->assertNull($budget->limitFor($this->organization->fresh(), AiFamily::Llm));
        $this->assertNull(data_get($this->organization->fresh()->settings, 'ai.budget.monthly_units.llm'));
    }

    public function test_negative_budget_is_rejected(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->put(route('admin.ai.budget.update'), ['monthly_units' => ['llm' => '-1']])
            ->assertSessionHasErrors('monthly_units.llm');
    }

    public function test_budget_needs_the_right_to_manage_ai_connections(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('admin.ai.budget.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.ai.budget.update'), ['monthly_units' => ['llm' => '1']])->assertForbidden();
    }
}
