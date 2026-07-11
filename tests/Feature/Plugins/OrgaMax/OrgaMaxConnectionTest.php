<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxConnectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\OrgaMax;

use App\Models\{OrgaMaxConnection, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-306/315: Verbindungsabsicht, iid-Callback (Anti-Fremd-iid),
 * Kontobestätigung, Scope-Preflight und Secret-Redaktion.
 */
class OrgaMaxConnectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    /** JWT mit exp-Claim (nur Struktur — Signatur wird nicht geprüft). */
    private function jwt(int $expiresInSeconds = 3600): string {
        $payload = rtrim(strtr(base64_encode((string) json_encode(['exp' => time() + $expiresInSeconds])), '+/', '-_'), '=');

        return 'eyJhbGciOiJIUzI1NiJ9.' . $payload . '.sig';
    }

    public function test_full_connection_flow_intent_callback_confirm(): void {
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/auth/token*' => FakePluginHttp::response(['token' => $this->jwt()]),
            'https://api.orgamax.de/openapi/setting/account*' => FakePluginHttp::response([
                'name' => 'Muster GmbH',
                'scopes' => ['customer:read', 'order:read', 'order:write', 'invoice:read'],
            ]),
        ]);

        // 1. Verbindungsabsicht (privater Pilotmodus).
        $this->post(route('admin.orgamax.connect'), [
            'mode' => 'private',
            'api_key' => 'key-1',
            'api_secret' => 'secret-1',
        ])->assertRedirect()->assertSessionHas('orgamax_callback_url');

        $callbackUrl = (string) session('orgamax_callback_url');
        $connection = OrgaMaxConnection::query()->firstOrFail();
        $this->assertSame(OrgaMaxConnection::STATUS_PENDING_CALLBACK, $connection->status);

        // 2. iid-Callback mit gültigem State-Token.
        $this->get($callbackUrl . '&iid=ownership-42')->assertRedirect(route('admin.orgamax.index'));
        $connection->refresh();
        $this->assertSame(OrgaMaxConnection::STATUS_PENDING_CONFIRMATION, $connection->status);
        $this->assertSame('Muster GmbH', $connection->account_snapshot['name'] ?? null);
        $this->assertNotNull($connection->bearer_token);

        // 3. Ausdrückliche Kontobestätigung → aktiv (keine Capability aktiviert → keine Scope-Lücke).
        $this->post(route('admin.orgamax.confirm'))->assertRedirect();
        $this->assertSame(OrgaMaxConnection::STATUS_ACTIVE, $connection->fresh()->status);
    }

    public function test_callback_with_wrong_state_binds_nothing(): void {
        $this->post(route('admin.orgamax.connect'), [
            'mode' => 'private',
            'api_key' => 'key-1',
            'api_secret' => 'secret-1',
        ]);

        $fake = FakePluginHttp::fake([]);

        $this->get(route('admin.orgamax.callback', ['state' => 'falsches-token', 'iid' => 'fremd-99']))
            ->assertRedirect(route('admin.orgamax.index'))
            ->assertSessionHas('error');

        $connection = OrgaMaxConnection::query()->firstOrFail();
        $this->assertSame(OrgaMaxConnection::STATUS_PENDING_CALLBACK, $connection->status);
        $this->assertNull($connection->bearer_token);
        $fake->assertNothingSent();
    }

    public function test_callback_without_intent_is_rejected(): void {
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'status' => OrgaMaxConnection::STATUS_ACTIVE,
        ]);

        $this->get(route('admin.orgamax.callback', ['state' => 'egal', 'iid' => 'fremd-1']))
            ->assertRedirect(route('admin.orgamax.index'))
            ->assertSessionHas('error');
    }

    public function test_missing_scopes_block_activation(): void {
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/auth/token*' => FakePluginHttp::response(['token' => $this->jwt()]),
            'https://api.orgamax.de/openapi/setting/account*' => FakePluginHttp::response([
                'name' => 'Muster GmbH',
                'scopes' => ['customer:read'], // order:*-Scopes fehlen
            ]),
        ]);

        $this->post(route('admin.orgamax.connect'), ['mode' => 'private', 'api_key' => 'k', 'api_secret' => 's']);
        $connection = OrgaMaxConnection::query()->firstOrFail();
        $this->get((string) session('orgamax_callback_url') . '&iid=own-1');

        // Faktura-Capability aktivieren → Preflight verlangt order:*-Scopes.
        $connection->refresh();
        $caps = $connection->capabilities;
        $caps['billing'] = ['enabled' => true, 'leader' => 'orgamax'];
        $connection->forceFill(['capabilities' => $caps])->save();

        $this->post(route('admin.orgamax.confirm'))->assertRedirect();

        $connection->refresh();
        $this->assertSame(OrgaMaxConnection::STATUS_BLOCKED, $connection->status);
        $this->assertStringContainsString('order:write', (string) $connection->blocked_reason);
    }

    public function test_secrets_never_appear_in_serialization_or_audit(): void {
        $connection = OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'api_key' => 'geheimer-key',
            'api_secret' => 'geheimes-secret',
            'ownership_id' => 'own-42',
            'bearer_token' => 'jwt-token',
            'status' => OrgaMaxConnection::STATUS_ACTIVE,
        ]);

        $serialized = json_encode($connection->toArray());
        $this->assertStringNotContainsString('geheimer-key', (string) $serialized);
        $this->assertStringNotContainsString('geheimes-secret', (string) $serialized);
        $this->assertStringNotContainsString('own-42', (string) $serialized);
        $this->assertStringNotContainsString('jwt-token', (string) $serialized);

        // Audit-Payload der Anlage enthält ebenfalls keine Secrets.
        $log = \App\Models\AuditLog::query()
            ->where('auditable_type', $connection->getMorphClass())
            ->where('auditable_id', $connection->id)
            ->latest('id')
            ->first();
        if ($log !== null) {
            $this->assertStringNotContainsString('geheimer-key', (string) json_encode($log->getAttribute('changes')));
        }
    }

    public function test_non_admin_cannot_manage_connection(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('admin.orgamax.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.orgamax.connect'), ['mode' => 'private'])->assertForbidden();
    }
}
