<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DutyPlanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\DutyPlan;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DutyPlanTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
    }

    private function admin(): User
    {
        $u = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        return $u;
    }

    private function user(): User
    {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    // ── Zugriffskontrolle ────────────────────────────────────────────────────

    public function test_guest_cannot_access_duty_plans(): void
    {
        $this->get(route('duty-plans.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_index(): void
    {
        $this->actingAs($this->user())
            ->get(route('duty-plans.index'))
            ->assertOk()
            ->assertViewIs('duty-plans.index');
    }

    public function test_non_admin_cannot_create(): void
    {
        $this->actingAs($this->user())
            ->get(route('duty-plans.create'))
            ->assertForbidden();
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_duty_plan(): void
    {
        $this->actingAs($this->admin())
            ->post(route('duty-plans.store'), [
                'title' => 'KW 21',
                'period_type' => DutyPlan::PERIOD_WEEKLY,
                'from_date' => '2026-05-18',
                'to_date' => '2026-05-24',
                'status' => DutyPlan::STATUS_DRAFT,
                'min_staff' => 2,
            ])
            ->assertRedirect(route('duty-plans.index'));

        $this->assertDatabaseHas('duty_plans', [
            'title' => 'KW 21',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_admin_can_update_duty_plan(): void
    {
        $plan = DutyPlan::factory()->draft()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('duty-plans.update', $plan), [
                'title' => 'KW 22 updated',
                'period_type' => DutyPlan::PERIOD_WEEKLY,
                'from_date' => $plan->from_date->toDateString(),
                'to_date' => $plan->to_date->toDateString(),
                'status' => DutyPlan::STATUS_DRAFT,
                'min_staff' => 1,
            ])
            ->assertRedirect(route('duty-plans.show', $plan));

        $this->assertDatabaseHas('duty_plans', ['id' => $plan->id, 'title' => 'KW 22 updated']);
    }

    public function test_admin_can_delete_draft(): void
    {
        $plan = DutyPlan::factory()->draft()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('duty-plans.destroy', $plan))
            ->assertRedirect(route('duty-plans.index'));

        $this->assertDatabaseMissing('duty_plans', ['id' => $plan->id]);
    }

    public function test_admin_cannot_delete_published_plan(): void
    {
        $plan = DutyPlan::factory()->published()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('duty-plans.destroy', $plan))
            ->assertForbidden();
    }

    // ── Publish / Retract ────────────────────────────────────────────────────

    public function test_admin_can_publish_duty_plan(): void
    {
        $plan = DutyPlan::factory()->draft()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('duty-plans.publish', $plan))
            ->assertRedirect();

        $this->assertSame(DutyPlan::STATUS_PUBLISHED, $plan->fresh()->status);
    }

    public function test_admin_can_retract_duty_plan(): void
    {
        $plan = DutyPlan::factory()->published()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('duty-plans.retract', $plan))
            ->assertRedirect();

        $this->assertSame(DutyPlan::STATUS_DRAFT, $plan->fresh()->status);
    }

    public function test_non_admin_cannot_publish(): void
    {
        $plan = DutyPlan::factory()->draft()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->user())
            ->patch(route('duty-plans.publish', $plan))
            ->assertForbidden();
    }

    // ── Mandanten-Isolierung ─────────────────────────────────────────────────

    public function test_plan_from_other_org_is_not_visible(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherPlan = DutyPlan::factory()->create(['organization_id' => $otherOrg->id]);

        // OrganizationScope filtert fremde Pläne heraus → 404, nicht 403
        $this->actingAs($this->user())
            ->get(route('duty-plans.show', $otherPlan))
            ->assertNotFound();
    }

    // ── Filter ───────────────────────────────────────────────────────────────

    public function test_index_filter_by_period_type(): void
    {
        DutyPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'period_type' => DutyPlan::PERIOD_WEEKLY,
            'title' => 'Weekly Plan',
        ]);
        DutyPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'period_type' => DutyPlan::PERIOD_MONTHLY,
            'title' => 'Monthly Plan',
        ]);

        $response = $this->actingAs($this->user())
            ->get(route('duty-plans.index', ['period' => DutyPlan::PERIOD_WEEKLY]));

        $response->assertOk();
        $plans = $response->viewData('plans');
        $this->assertTrue($plans->every(fn (DutyPlan $p) => $p->period_type === DutyPlan::PERIOD_WEEKLY));
    }
}
