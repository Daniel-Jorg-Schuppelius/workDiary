<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiAdminHttpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\Ai\AiConnectionStatus;
use App\Enums\User\Permission;
use App\Models\Ai\{AiCapabilitySetting, AiMemoryEntry, AiProviderConnection};
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{RegistersAiCapabilities, WithOrganization};
use Tests\Support\FakeAiProviderFactory;
use Tests\TestCase;

/**
 * Admin-Seite „KI-Dienste" + Gedächtnis (Feature 025, MVP-400/401):
 * Rechte-Gating, Plan-Gating (423), Verbindungs-Lifecycle mit Preflight,
 * Capability-Routing, Gedächtnis-Pflege und Security-Übersicht.
 */
class AiAdminHttpTest extends TestCase {
    use RefreshDatabase;
    use RegistersAiCapabilities;
    use WithOrganization;

    private const CAPABILITY = 'test.formulate';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        FakeAiProviderFactory::install();

        $this->registerAiCapability(self::CAPABILITY, ['data_classes' => ['leistungstext'], 'memory_scopes' => ['organization', 'customer']]);

        $this->admin = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin->givePermissionTo([Permission::AiManage->value]);
    }

    public function test_user_without_permission_is_forbidden(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('admin.ai.index'))->assertForbidden();
        $this->actingAs($stranger)->get(route('admin.ai.memory'))->assertForbidden();
    }

    public function test_free_plan_blocks_ai_module_with_423(): void {
        $freeOrg = Organization::factory()->free()->create();
        app()->instance('currentOrganization', $freeOrg);
        $user = User::factory()->user()->create(['organization_id' => $freeOrg->id]);
        $user->givePermissionTo(Permission::AiManage->value);

        $this->actingAs($user)->get(route('admin.ai.index'))->assertStatus(423);
    }

    public function test_index_renders_connections_and_capability_matrix(): void {
        AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Lokales Ollama',
            'is_local' => true,
        ]);

        $this->actingAs($this->admin)->get(route('admin.ai.index'))
            ->assertOk()
            ->assertSee('Lokales Ollama')
            ->assertSee(self::CAPABILITY);
    }

    public function test_store_creates_connection_and_runs_preflight(): void {
        $this->actingAs($this->admin)->post(route('admin.ai.store'), [
            'name' => 'Fake-Provider',
            'family' => 'llm',
            'provider' => 'fake',
            'api_key' => 'sk-test',
        ])->assertRedirect(route('admin.ai.index'));

        $connection = AiProviderConnection::query()->where('name', 'Fake-Provider')->firstOrFail();
        $this->assertSame(AiConnectionStatus::Active, $connection->status);
        $this->assertNotNull($connection->preflight_at);
    }

    public function test_store_rejects_family_mismatch(): void {
        $this->actingAs($this->admin)->post(route('admin.ai.store'), [
            'name' => 'DeepL falsch',
            'family' => 'llm',
            'provider' => 'deepl',
        ])->assertRedirect();

        $this->assertDatabaseMissing('ai_provider_connections', ['name' => 'DeepL falsch']);
    }

    public function test_store_requires_a_model_for_llm_providers(): void {
        // Ohne Modell scheitert erst der Provider-Aufruf — die Meldung soll am
        // Formular stehen, nicht später als Verbindungsfehler.
        $this->actingAs($this->admin)->post(route('admin.ai.store'), [
            'name' => 'OpenAI ohne Modell',
            'family' => 'llm',
            'provider' => 'openai',
            'api_key' => 'sk-test',
        ])->assertSessionHasErrors('model');

        $this->assertDatabaseMissing('ai_provider_connections', ['name' => 'OpenAI ohne Modell']);
    }

    public function test_edit_and_update_repair_a_disabled_connection(): void {
        $connection = AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'ChatGPT',
        ]);
        $connection->forceFill([
            'last_error' => 'openai: HTTP 429',
            'last_error_at' => now(),
            'consecutive_failures' => 10,
            'disabled_at' => now(),
        ])->save();

        $this->actingAs($this->admin)->get(route('admin.ai.edit', $connection))->assertOk()->assertSee('ChatGPT');

        $this->actingAs($this->admin)->patch(route('admin.ai.update', $connection), [
            'name' => 'ChatGPT',
            'model' => 'gpt-4o-mini',
        ])->assertRedirect(route('admin.ai.index'));

        $fresh = $connection->fresh();
        $this->assertSame('gpt-4o-mini', $fresh->model);
        $this->assertNull($fresh->last_error);
        $this->assertSame(0, (int) $fresh->consecutive_failures);
        $this->assertNull($fresh->disabled_at, 'Reparatur muss die Auto-Abschaltung aufheben.');
    }

    public function test_block_and_unblock_lifecycle(): void {
        $connection = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->post(route('admin.ai.block', $connection))->assertRedirect();
        $this->assertSame(AiConnectionStatus::Blocked, $connection->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.ai.unblock', $connection))->assertRedirect();
        // Entsperren aktiviert NICHT — erst ein neuer Preflight tut das.
        $this->assertSame(AiConnectionStatus::Draft, $connection->fresh()->status);
    }

    public function test_capability_update_persists_routing_and_ignores_foreign_connections(): void {
        $own = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $foreignOrg = Organization::factory()->create();
        $foreign = AiProviderConnection::factory()->create(['organization_id' => $foreignOrg->id]);

        $this->actingAs($this->admin)->post(
            route('admin.ai.capability.update', ['capability' => self::CAPABILITY]),
            [
                'enabled' => 1,
                'allow_user_choice' => 1,
                'allowed_connection_ids' => [$own->sqid, $foreign->sqid],
                'default_connection_id' => $own->sqid,
            ]
        )->assertRedirect(route('admin.ai.index'));

        $setting = AiCapabilitySetting::query()->where('capability', self::CAPABILITY)->firstOrFail();
        $this->assertTrue($setting->enabled);
        // Fremde Verbindung fliegt raus (Mandantengrenze).
        $this->assertSame([(int) $own->id], array_map('intval', (array) $setting->allowed_connection_ids));
        $this->assertSame((int) $own->id, (int) $setting->default_connection_id);
    }

    public function test_preview_shows_data_flow_without_calling_provider(): void {
        $connection = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => self::CAPABILITY,
            'enabled' => true,
            'allowed_connection_ids' => [$connection->id],
        ]);

        $fake = FakeAiProviderFactory::install();

        $this->actingAs($this->admin)
            ->get(route('admin.ai.capability.preview', ['capability' => self::CAPABILITY]))
            ->assertOk()
            ->assertSee('leistungstext');

        $this->assertSame(0, $fake->callCount());
    }

    public function test_unknown_capability_returns_404_instead_of_500(): void {
        $this->actingAs($this->admin)
            ->get(route('admin.ai.capability.preview', ['capability' => 'nicht.registriert']))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->post(route('admin.ai.capability.update', ['capability' => 'nicht.registriert']), ['enabled' => 1])
            ->assertNotFound();
    }

    public function test_memory_crud_via_http(): void {
        $this->actingAs($this->admin)->post(route('admin.ai.memory.store'), [
            'entry_type' => 'glossary',
            'scope' => 'organization',
            'term' => 'snap',
            'content' => 'Snap-Paketverwaltung',
            'translation_en' => 'snap package management',
        ])->assertRedirect(route('admin.ai.memory'));

        $entry = AiMemoryEntry::query()->where('term', 'snap')->firstOrFail();
        $this->assertSame(['en' => 'snap package management'], $entry->translations);
        $this->assertSame(AiMemoryEntry::ORIGIN_MANUAL, $entry->origin);

        $this->actingAs($this->admin)->get(route('admin.ai.memory'))->assertOk()->assertSee('snap');

        $this->actingAs($this->admin)->post(route('admin.ai.memory.toggle', $entry))->assertRedirect();
        $this->assertFalse($entry->fresh()->active);

        $this->actingAs($this->admin)->delete(route('admin.ai.memory.destroy', $entry))->assertRedirect();
        $this->assertDatabaseMissing('ai_memory_entries', ['id' => $entry->id]);
    }

    public function test_memory_store_validates_type_requirements(): void {
        $this->actingAs($this->admin)->post(route('admin.ai.memory.store'), [
            'entry_type' => 'glossary',
            'scope' => 'organization',
            'content' => 'ohne Begriff',
        ])->assertRedirect();

        $this->assertDatabaseMissing('ai_memory_entries', ['content' => 'ohne Begriff']);
    }

    public function test_security_page_lists_active_ai_connections(): void {
        AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Aktiver Fake',
            'status' => AiConnectionStatus::Active,
        ]);

        $securityAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($securityAdmin)->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee(__('ai.security.active_connections'));
    }
}
