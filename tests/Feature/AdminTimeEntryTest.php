<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdminTimeEntryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\ActivityCategory;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use App\Enums\Activity\ActivityCategoryType;
use App\Enums\TimeEntry\TimeEntryActivityType;

class AdminTimeEntryTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->seed(ActivityCategorySeeder::class);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_create_form_renders(): void
    {
        $this->actingAs($this->user);
        $this->get(route('admin-time-entries.create'))
            ->assertOk()
            ->assertSee(__('Verwaltungszeit erfassen'));
    }

    public function test_store_creates_admin_entry_without_project(): void
    {
        $this->actingAs($this->user);

        $cat = ActivityCategory::query()->where('key', 'administration')->first();

        $this->post(route('admin-time-entries.store'), [
            'date' => now()->toDateString(),
            'minutes' => 45,
            'activity_type' => TimeEntryActivityType::Admin->value,
            'activity_category_id' => $cat?->id,
            'description' => 'E-Mails sortiert',
        ])->assertRedirect();

        $this->assertDatabaseHas('time_entries', [
            'user_id' => $this->user->id,
            'project_id' => null,
            'activity_type' => TimeEntryActivityType::Admin->value,
            'minutes' => 45,
        ]);
    }

    public function test_store_rejects_invalid_activity_type(): void
    {
        $this->actingAs($this->user);

        $this->from(route('admin-time-entries.create'))
            ->post(route('admin-time-entries.store'), [
                'date' => now()->toDateString(),
                'minutes' => 10,
                'activity_type' => TimeEntryActivityType::Project->value,
            ])
            ->assertRedirect(route('admin-time-entries.create'))
            ->assertSessionHasErrors('activity_type');
    }

    public function test_activity_categories_index_requires_admin_for_writes(): void
    {
        $this->actingAs($this->user);

        $this->get(route('activity-categories.index'))->assertOk();

        // non-admin POST → forbidden by policy
        $this->from(route('activity-categories.index'))
            ->post(route('activity-categories.store'), [
                'key' => 'custom_one',
                'label' => 'Custom',
                'activity_type' => ActivityCategoryType::Other->value,
            ])
            ->assertForbidden();

        // admin can create
        $this->actingAs($this->admin);
        $this->post(route('activity-categories.store'), [
            'key' => 'custom_two',
            'label' => 'Custom Two',
            'activity_type' => ActivityCategoryType::Other->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_categories', ['key' => 'custom_two']);
    }
}
