<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketWriteApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\ServiceTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-17 (authz-api-ticket-1): `POST /tickets` hing
 * allein am Token-Scope `tickets:write` — das Recht am Vorgang prüfte
 * niemand. Ein Token, das eine Person sich selbst ausstellt, erhielt damit
 * mehr als die Person im Web hat.
 */
final class TicketWriteApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_ticket_creation_requires_the_create_permission(): void {
        $ohneRecht = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs($ohneRecht, ['tickets:write']);

        $this->postJson(route('api.tickets.store'), ['title' => 'Aus der API'])->assertForbidden();

        $this->assertSame(0, ServiceTicket::query()->count());
    }

    public function test_ticket_creation_works_with_the_create_permission(): void {
        $berechtigt = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $berechtigt->givePermissionTo(Permission::ServiceTicketCreate->value);
        Sanctum::actingAs($berechtigt, ['tickets:write']);

        $this->postJson(route('api.tickets.store'), ['title' => 'Aus der API'])
            ->assertCreated()
            ->assertJsonStructure(['id', 'ticket_no', 'status']);

        $this->assertSame(1, ServiceTicket::query()->count());
    }
}
