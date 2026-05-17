<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceOrderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ServiceOrderTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
    }

    public function test_user_can_create_service_order(): void
    {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('service-orders.store'), [
                'title' => 'Heizung warten',
                'scheduled_for' => '2026-06-01',
                'service_minutes' => 90,
                'priority' => ServiceOrder::PRIORITY_HIGH,
                'address_country' => 'DE',
            ])
            ->assertRedirect(route('service-orders.index'));

        $this->assertDatabaseHas('service_orders', [
            'title' => 'Heizung warten',
            'priority' => ServiceOrder::PRIORITY_HIGH,
            'organization_id' => $this->organization->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_user_cannot_view_order_of_other_user(): void
    {
        $owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $order = ServiceOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'assigned_user_id' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('service-orders.show', $order))
            ->assertForbidden();
    }

    public function test_admin_can_filter_by_user(): void
    {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        ServiceOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'assigned_user_id' => $worker->id,
            'title' => 'A-Auftrag',
        ]);

        $this->actingAs($admin)
            ->get(route('service-orders.index', ['user' => $worker->id]))
            ->assertOk()
            ->assertSee('A-Auftrag');
    }

    public function test_non_admin_cannot_view_user_all(): void
    {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('service-orders.index', ['user' => 'all']))
            ->assertForbidden();
    }

    public function test_user_can_delete_own_order(): void
    {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $order = ServiceOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'assigned_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('service-orders.destroy', $order))
            ->assertRedirect(route('service-orders.index'));

        $this->assertDatabaseMissing('service_orders', ['id' => $order->id]);
    }
}
