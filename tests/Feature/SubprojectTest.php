<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SubprojectTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'hourly_rate' => 80,
            'internal_rate' => 30,
        ]);
    }

    private function makeProject(array $attrs = []): Project {
        return Project::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Projekt ' . uniqid('', true),
            'status' => Project::STATUS_ACTIVE,
        ], $attrs));
    }

    public function test_sub_project_inherits_customer_from_parent(): void {
        $parent = $this->makeProject();

        $child = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => null,
            'parent_id' => $parent->id,
            'name' => 'Sub ' . uniqid('', true),
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->assertSame((int) $parent->customer_id, (int) $child->customer_id);
    }

    public function test_sub_project_with_mismatched_customer_is_overridden_by_parent(): void {
        $parent = $this->makeProject();
        $other = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $child = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $other->id,
            'parent_id' => $parent->id,
            'name' => 'Sub ' . uniqid('', true),
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->assertSame((int) $parent->customer_id, (int) $child->customer_id);
    }

    public function test_self_parent_is_rejected_via_request(): void {
        $project = $this->makeProject();

        $this->actingAs($this->user)
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'status' => Project::STATUS_ACTIVE,
                'parent_id' => $project->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_descendant_as_parent_is_rejected_via_request(): void {
        $parent = $this->makeProject(['name' => 'Root A']);
        $child = Project::create([
            'organization_id' => $this->organization->id,
            'parent_id' => $parent->id,
            'name' => 'Child A',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->user)
            ->put(route('projects.update', $parent), [
                'name' => $parent->name,
                'status' => Project::STATUS_ACTIVE,
                'parent_id' => $child->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_is_default_on_sub_project_is_forced_to_false(): void {
        $parent = $this->makeProject();

        $child = Project::create([
            'organization_id' => $this->organization->id,
            'parent_id' => $parent->id,
            'name' => 'Child default attempt',
            'status' => Project::STATUS_ACTIVE,
            'is_default' => true,
        ]);

        $this->assertFalse((bool) $child->fresh()->is_default);
    }

    public function test_effective_hourly_rate_walks_up_to_parent_then_customer(): void {
        $parent = $this->makeProject(['hourly_rate' => 120]);
        $child = Project::create([
            'organization_id' => $this->organization->id,
            'parent_id' => $parent->id,
            'name' => 'Child no rate',
            'status' => Project::STATUS_ACTIVE,
        ]);
        $grandchild = Project::create([
            'organization_id' => $this->organization->id,
            'parent_id' => $child->id,
            'name' => 'Grandchild no rate',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->assertSame(120.0, $grandchild->effectiveHourlyRate());

        $standalone = $this->makeProject();
        $this->assertSame(80.0, $standalone->effectiveHourlyRate());
    }

    public function test_delete_with_children_is_blocked_by_policy(): void {
        $parent = $this->makeProject();
        Project::create([
            'organization_id' => $this->organization->id,
            'parent_id' => $parent->id,
            'name' => 'Child blocking delete',
            'status' => Project::STATUS_ACTIVE,
        ]);

        // Non-admin user -> policy returns false (children present)
        $this->actingAs($this->user)
            ->delete(route('projects.destroy', $parent))
            ->assertForbidden();
    }
}
