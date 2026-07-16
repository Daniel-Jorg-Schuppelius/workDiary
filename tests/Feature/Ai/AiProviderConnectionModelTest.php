<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiProviderConnectionModelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\Ai\AiProviderType;
use App\Models\Ai\AiProviderConnection;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Datenmodell-Garantien der Provider-Verbindung (MVP-399): Schlüssel
 * verschlüsselt + nie serialisiert, Mandanten-Scope, erzwungene
 * Lokalitäts-Defaults.
 */
class AiProviderConnectionModelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_api_key_is_encrypted_at_rest_and_hidden_in_serialization(): void {
        $connection = AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'api_key' => 'sk-super-geheim',
        ]);

        $raw = DB::table('ai_provider_connections')->where('id', $connection->id)->value('api_key');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('sk-super-geheim', (string) $raw);

        $serialized = (string) json_encode($connection->fresh());
        $this->assertStringNotContainsString('api_key', $serialized);
        $this->assertStringNotContainsString('sk-super-geheim', $serialized);

        $this->assertSame('sk-super-geheim', $connection->fresh()->api_key);
    }

    public function test_global_scope_isolates_organizations(): void {
        AiProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $otherOrg = Organization::factory()->create();
        AiProviderConnection::factory()->create(['organization_id' => $otherOrg->id]);

        // currentOrganization ist per WithOrganization gebunden — der
        // globale Scope darf nur die eigene Verbindung liefern.
        $this->assertSame(1, AiProviderConnection::query()->count());
    }

    public function test_locality_default_is_enforced_per_provider_type(): void {
        $ollama = AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::Ollama,
            'is_local' => false, // Manipulationsversuch — Typ erzwingt lokal.
        ]);
        $this->assertTrue($ollama->fresh()->is_local);

        $anthropic = AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::Anthropic,
            'is_local' => true, // Manipulationsversuch — Typ erzwingt Cloud.
        ]);
        $this->assertFalse($anthropic->fresh()->is_local);

        $generic = AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::OpenAiCompatible,
            'is_local' => true, // bewusste Admin-Einstufung bleibt erhalten.
        ]);
        $this->assertTrue($generic->fresh()->is_local);
    }
}
