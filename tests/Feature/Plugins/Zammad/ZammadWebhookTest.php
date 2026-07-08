<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Zammad;

use App\Models\{Task, ZammadConnection};
use App\Plugins\Zammad\Contracts\{ZammadGateway, ZammadGatewayFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 060, MVP-129: Webhook-Endpunkt. Autorisierung ausschließlich über die
 * HMAC-Signatur (X-Hub-Signature: sha1=…) des Raw-Bodys; ein gültiger Anstoß
 * löst einen idempotenten Import aus, ungültige/unbekannte Anfragen werden ohne
 * Verarbeitung abgewiesen.
 */
final class ZammadWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const SECRET = 'whsec-super';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->fakeFactory();
    }

    private function fakeFactory(): void {
        $gateway = new class implements ZammadGateway {
            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return [['id' => 1, 'number' => '22001', 'title' => 'Drucker', 'group_id' => null, 'state' => 'open', 'customer_id' => null]];
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

        $this->app->instance(ZammadGatewayFactory::class, new class($gateway) implements ZammadGatewayFactory {
            public function __construct(private ZammadGateway $gateway) {}

            public function for(ZammadConnection $connection): ZammadGateway {
                return $this->gateway;
            }
        });
    }

    private function connection(bool $active = true, ?string $secret = self::SECRET): ZammadConnection {
        return ZammadConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'base_url' => 'https://support.example.com',
            'api_token' => 'token-123',
            'webhook_secret' => $secret,
            'active' => $active,
        ]);
    }

    /** @return TestResponse<\Illuminate\Http\Response> */
    private function sendWebhook(int $connectionId, string $body, ?string $signature): TestResponse {
        return $this->call('POST', "/api/webhooks/zammad/{$connectionId}", [], [], [], [
            'HTTP_X-Hub-Signature' => $signature ?? '',
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_valid_signature_triggers_import(): void {
        $connection = $this->connection();
        $body = (string) json_encode(['ticket' => ['id' => 1]]);
        $signature = 'sha1=' . hash_hmac('sha1', $body, self::SECRET);

        $this->sendWebhook((int) $connection->id, $body, $signature)
            ->assertOk()
            ->assertJson(['status' => 'ok', 'created' => 1]);

        $this->assertSame(1, Task::query()->count());
    }

    public function test_invalid_signature_is_rejected(): void {
        $connection = $this->connection();
        $body = (string) json_encode(['ticket' => ['id' => 1]]);

        $this->sendWebhook((int) $connection->id, $body, 'sha1=deadbeef')->assertForbidden();
        $this->assertSame(0, Task::query()->count());
    }

    public function test_unknown_connection_is_ignored(): void {
        $body = (string) json_encode(['ticket' => ['id' => 1]]);
        $signature = 'sha1=' . hash_hmac('sha1', $body, self::SECRET);

        $this->sendWebhook(999999, $body, $signature)->assertNotFound();
    }

    public function test_connection_without_secret_is_ignored(): void {
        $connection = $this->connection(secret: null);
        $body = (string) json_encode(['ticket' => ['id' => 1]]);
        $signature = 'sha1=' . hash_hmac('sha1', $body, self::SECRET);

        $this->sendWebhook((int) $connection->id, $body, $signature)->assertNotFound();
        $this->assertSame(0, Task::query()->count());
    }
}
