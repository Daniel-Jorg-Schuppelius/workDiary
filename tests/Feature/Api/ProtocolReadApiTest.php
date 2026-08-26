<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolReadApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Protocol\{ProtocolSignatureMethod, ProtocolSignatureRole, ProtocolType};
use App\Models\{Organization, Protocol, ProtocolSignature, ProtocolSignatureToken, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-718 (Vollscan J11): Read-only-REST Protokolle ohne Signatur-Token-Interna. */
final class ProtocolReadApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    public function test_missing_ability_is_forbidden(): void {
        Sanctum::actingAs($this->admin, ['diary:read']);

        $this->getJson(route('api.protocols.index'))->assertForbidden();
    }

    public function test_index_filters_and_paginates(): void {
        Protocol::factory()->count(2)->create(['organization_id' => $this->organization->id, 'created_by_user_id' => $this->admin->id, 'type' => ProtocolType::Service->value]);
        Protocol::factory()->signed()->create(['organization_id' => $this->organization->id, 'created_by_user_id' => $this->admin->id, 'type' => ProtocolType::Acceptance->value, 'title' => 'Abnahme Halle 2']);
        Sanctum::actingAs($this->admin, ['protocols:read']);

        $page = $this->getJson(route('api.protocols.index', ['per_page' => 2]))->assertOk();
        $this->assertCount(2, $page->json('data'));
        $this->assertSame(3, $page->json('meta.total'));

        $byType = $this->getJson(route('api.protocols.index', ['type' => 'acceptance']))->assertOk();
        $this->assertCount(1, $byType->json('data'));
        $this->assertSame('Abnahme Halle 2', $byType->json('data.0.title'));

        $this->assertCount(1, $this->getJson(route('api.protocols.index', ['status' => 'signed']))->json('data'));
        $this->assertCount(1, $this->getJson(route('api.protocols.index', ['search' => 'Halle']))->json('data'));
    }

    public function test_worker_without_view_any_sees_only_own_protocols(): void {
        $worker = $this->orgUser();
        Protocol::factory()->create(['organization_id' => $this->organization->id, 'created_by_user_id' => $worker->id, 'title' => 'Eigenes']);
        $foreign = Protocol::factory()->create(['organization_id' => $this->organization->id, 'created_by_user_id' => $this->admin->id, 'title' => 'Fremdes']);
        Sanctum::actingAs($worker, ['protocols:read']);

        $list = $this->getJson(route('api.protocols.index'))->assertOk();
        $this->assertSame(['Eigenes'], array_column($list->json('data'), 'title'));
        $this->getJson(route('api.protocols.show', $foreign))->assertForbidden();
    }

    public function test_show_exposes_signatures_without_token_internals(): void {
        $protocol = Protocol::factory()->signed()->create(['organization_id' => $this->organization->id, 'created_by_user_id' => $this->admin->id]);
        ProtocolSignature::query()->create([
            'protocol_id' => $protocol->id,
            'role' => ProtocolSignatureRole::Customer->value,
            'signer_name' => 'Kunde Muster',
            'signer_email' => 'kunde@example.test',
            'signed_at' => now(),
            'method' => ProtocolSignatureMethod::Onscreen->value,
            'ip' => '203.0.113.7',
            'user_agent' => 'UA-Secret',
            'hash' => str_repeat('a', 64),
        ]);
        ProtocolSignatureToken::query()->create([
            'protocol_id' => $protocol->id,
            'role' => ProtocolSignatureRole::Customer->value,
            'token_hash' => str_repeat('b', 64),
            'expires_at' => now()->addDay(),
            'created_by_user_id' => $this->admin->id,
        ]);
        Sanctum::actingAs($this->admin, ['protocols:read']);

        $response = $this->getJson(route('api.protocols.show', $protocol))->assertOk();
        $response->assertJsonPath('data.id', $protocol->sqid)
            ->assertJsonPath('data.status', 'signed')
            ->assertJsonPath('data.signatures.0.signer_name', 'Kunde Muster')
            ->assertJsonPath('data.signatures.0.role', 'customer')
            ->assertJsonMissingPath('data.signatures.0.ip')
            ->assertJsonMissingPath('data.signatures.0.hash')
            ->assertJsonMissingPath('data.signature_tokens');
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('token_hash', $body);
        $this->assertStringNotContainsString(str_repeat('b', 64), $body);
        $this->assertStringNotContainsString('UA-Secret', $body);
        $this->assertStringNotContainsString('203.0.113.7', $body);
    }

    public function test_foreign_organization_protocol_is_not_found(): void {
        $other = Organization::factory()->create();
        $foreign = Protocol::factory()->create(['organization_id' => $other->id]);
        Sanctum::actingAs($this->admin, ['protocols:read']);

        $this->getJson(route('api.protocols.show', $foreign))->assertNotFound();
    }
}
