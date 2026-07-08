<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HookApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Api;

use App\Enums\Integration\WebhookEvent;
use App\Models\Integration\WebhookEndpoint;
use App\Models\{Organization, User};
use App\Services\Integration\WebhookDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature 008 → Rang 61: öffentliche REST-Hooks-API (n8n/Make/Zapier). Prüft
 * Subscribe (201 + id + einmaliges Secret), Liste (ohne Secret), Unsubscribe
 * (204 + Soft-Delete), Event-Katalog (Sample = Live-Schema), Ability-Schutz
 * (`hooks:manage`), Mandantengrenze und SSRF-Ablehnung.
 */
class HookApiTest extends TestCase {
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_subscribe_returns_201_with_id_and_one_time_secret(): void {
        Sanctum::actingAs($this->user, ['hooks:manage']);

        $response = $this->postJson('/api/hooks', [
            'event' => WebhookEvent::SlaBreached->value,
            'target_url' => 'https://hooks.example.com/inbox',
        ]);

        $response->assertCreated()
            ->assertJsonPath('events.0', WebhookEvent::SlaBreached->value)
            ->assertJsonPath('target_url', 'https://hooks.example.com/inbox');
        $this->assertNotEmpty($response->json('id'));
        $this->assertNotEmpty($response->json('secret'));          // Klartext genau einmal
        $this->assertNotEmpty($response->json('signature.header'));

        $this->assertDatabaseHas('webhook_endpoints', [
            'organization_id' => $this->organization->id,
            'url' => 'https://hooks.example.com/inbox',
        ]);
    }

    public function test_list_hides_secret_but_shows_preview(): void {
        WebhookEndpoint::factory()->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs($this->user, ['hooks:manage']);

        $response = $this->getJson('/api/hooks')->assertOk();

        $response->assertJsonMissingPath('data.0.secret');
        $this->assertNotNull($response->json('data.0.secret_preview'));
    }

    public function test_unsubscribe_returns_204_and_soft_deletes(): void {
        $hook = WebhookEndpoint::factory()->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs($this->user, ['hooks:manage']);

        $this->deleteJson('/api/hooks/' . $hook->sqid)->assertNoContent();
        $this->assertSoftDeleted('webhook_endpoints', ['id' => $hook->id]);
    }

    public function test_event_catalog_sample_matches_live_schema(): void {
        Sanctum::actingAs($this->user, ['hooks:manage']);

        $response = $this->getJson('/api/hooks/events')->assertOk();

        $sample = $response->json('data.0.sample_payload');
        // Exakt die Live-Hülle aus WebhookDispatchService::buildPayload.
        $this->assertSame(['event', 'occurred_at', 'organization', 'data'], array_keys($sample));

        $live = app(WebhookDispatchService::class)->buildPayload(
            WebhookEvent::cases()[0],
            $this->organization->id,
            WebhookEvent::cases()[0]->sampleData(),
            now(),
        );
        $this->assertSame(array_keys($live), array_keys($sample));
        $this->assertSame(array_keys($live['data']), array_keys($sample['data']));
    }

    public function test_requires_hooks_manage_ability(): void {
        Sanctum::actingAs($this->user, ['diary:read']); // NICHT hooks:manage

        $this->getJson('/api/hooks')->assertForbidden();
        $this->postJson('/api/hooks', [
            'event' => WebhookEvent::SlaBreached->value,
            'target_url' => 'https://hooks.example.com/x',
        ])->assertForbidden();
    }

    public function test_foreign_hook_is_not_found(): void {
        $otherOrg = Organization::factory()->create();
        $foreign = WebhookEndpoint::factory()->create(['organization_id' => $otherOrg->id]);
        Sanctum::actingAs($this->user, ['hooks:manage']);

        $this->deleteJson('/api/hooks/' . $foreign->sqid)->assertNotFound();
    }

    public function test_rejects_non_public_target_url(): void {
        Sanctum::actingAs($this->user, ['hooks:manage']);

        $this->postJson('/api/hooks', [
            'event' => WebhookEvent::SlaBreached->value,
            'target_url' => 'http://127.0.0.1/hook',
        ])->assertStatus(422);
    }
}
