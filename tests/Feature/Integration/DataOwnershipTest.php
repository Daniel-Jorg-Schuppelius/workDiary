<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataOwnershipTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Integration;

use App\Enums\Integration\DataDomain;
use App\Models\{AuditLog, Organization, Task, User};
use App\Services\Integration\DataOwnershipResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Restpunkt 69: zentrale Datenführerschaft — genau ein Owner je Bereich
 * (Map, Doppel-Führung strukturell unmöglich), native Führung erlaubt
 * Plugin-Importe (Bestandsverhalten), Plugin-Führung blockt fremde Plugins;
 * Admin-Matrix mit Audit.
 */
final class DataOwnershipTest extends TestCase {
    use RefreshDatabase;

    public function test_single_owner_per_domain_and_write_gate(): void {
        $org = Organization::factory()->create();
        $resolver = app(DataOwnershipResolver::class);

        // Default: native — alle dürfen schreiben (Importe wie bisher).
        $this->assertSame('native', $resolver->ownerFor($org, DataDomain::Tasks));
        $this->assertTrue($resolver->mayWrite($org, DataDomain::Tasks, 'zammad'));

        // Plugin-Führung: nur der Owner (und native) schreiben.
        $resolver->setOwner($org, DataDomain::Tasks, 'openproject');
        $org->refresh();
        $this->assertSame('openproject', $resolver->ownerFor($org, DataDomain::Tasks));
        $this->assertTrue($resolver->mayWrite($org, DataDomain::Tasks, 'openproject'));
        $this->assertTrue($resolver->mayWrite($org, DataDomain::Tasks, 'native'));
        $this->assertFalse($resolver->mayWrite($org, DataDomain::Tasks, 'zammad'));

        // Umsetzen ersetzt den Owner (nie zwei gleichzeitig).
        $resolver->setOwner($org, DataDomain::Tasks, 'zammad');
        $this->assertSame('zammad', $resolver->ownerFor($org->refresh(), DataDomain::Tasks));

        // Zurück auf native entfernt den Eintrag.
        $resolver->setOwner($org, DataDomain::Tasks, 'native');
        $this->assertSame('native', $resolver->ownerFor($org->refresh(), DataDomain::Tasks));
        $this->assertArrayNotHasKey('data_ownership', (array) $org->settings);
    }

    public function test_zammad_write_against_foreign_ownership_lands_in_inbox(): void {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        app(DataOwnershipResolver::class)->setOwner($org, DataDomain::Tasks, 'openproject');

        $connection = \App\Models\ZammadConnection::query()->create([
            'organization_id' => $org->id,
            'name' => 'Support',
            'base_url' => 'https://support.example.com',
            'api_token' => 'token-123',
            'active' => true,
        ]);

        $gateway = new class implements \App\Plugins\Zammad\Contracts\ZammadGateway {
            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return [['id' => 1, 'number' => '22001', 'title' => 'Ticket 1', 'group_id' => 5, 'state' => 'open', 'customer_id' => 3]];
            }

            public function ping(): bool {
                return true;
            }

            public function updateTicketState(int $ticketId, ?string $state, ?string $note): bool {
                return true;
            }

            public function accountTime(int $ticketId, float $timeUnit): bool {
                return true;
            }

            public function addArticle(int $ticketId, string $body, bool $internal = true): bool {
                return true;
            }
        };

        $result = (new \App\Plugins\Zammad\Services\ZammadTicketImporter)->import($connection, $gateway);

        $this->assertSame(0, Task::query()->count(), 'Fremdgeführter Bereich: kein Task-Write.');
        $this->assertSame(1, $result['inbox']);
        $this->assertSame(
            1,
            \App\Models\IntegrationInboxItem::query()
                ->where('external_type', 'ticket_ownership_conflict')->count(),
        );
    }

    public function test_admin_matrix_updates_with_audit(): void {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)->get(route('admin.data-ownership.index'))
            ->assertOk()
            ->assertSee('Datenführerschaft');

        $this->actingAs($admin)->post(route('admin.data-ownership.update'), [
            'domain' => 'tickets',
            'owner' => 'zammad',
        ])->assertRedirect(route('admin.data-ownership.index'));

        $this->assertSame('zammad', app(DataOwnershipResolver::class)->ownerFor($org->refresh(), DataDomain::Tickets));
        $this->assertSame(1, AuditLog::query()->where('event', 'integration.data_ownership_changed')->count());
    }
}
