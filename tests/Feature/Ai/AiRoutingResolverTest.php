<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiRoutingResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection};
use App\Models\Organization;
use App\Services\Ai\AiRoutingResolver;
use App\Services\Ai\Exceptions\AiUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Routing-Regeln des KI-Fundaments (Feature 025, MVP-399): Modul-Gate,
 * Capability-Opt-in, erlaubte Verbindungen, Sensibilitäts-/Profil-Gate,
 * Nutzerwahl und Fallback-Reihenfolge.
 */
class AiRoutingResolverTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const CAPABILITY = 'test.formulate';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        config()->set('ai.capabilities.' . self::CAPABILITY, [
            'verb' => 'formulate',
            'sensitivity' => 'medium',
            'data_classes' => ['text'],
            'memory_scopes' => [],
            'prompt_version' => 1,
        ]);
    }

    private function resolver(): AiRoutingResolver {
        return app(AiRoutingResolver::class);
    }

    private function connection(array $attributes = []): AiProviderConnection {
        return AiProviderConnection::factory()->create(array_merge(
            ['organization_id' => $this->organization->id],
            $attributes
        ));
    }

    private function enableCapability(array $attributes = []): AiCapabilitySetting {
        return AiCapabilitySetting::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'capability' => self::CAPABILITY,
            'enabled' => true,
        ], $attributes));
    }

    public function test_free_plan_blocks_ai_module(): void {
        $freeOrg = Organization::factory()->free()->create();
        $connection = AiProviderConnection::factory()->create(['organization_id' => $freeOrg->id]);
        AiCapabilitySetting::factory()->create([
            'organization_id' => $freeOrg->id,
            'capability' => self::CAPABILITY,
            'enabled' => true,
            'allowed_connection_ids' => [$connection->id],
        ]);

        try {
            $this->resolver()->resolve($freeOrg, self::CAPABILITY);
            $this->fail('Modul-Gate hat nicht gegriffen.');
        } catch (AiUnavailableException $e) {
            $this->assertSame(AiUnavailableException::REASON_MODULE_INACTIVE, $e->reason);
        }
    }

    public function test_capability_is_disabled_by_default(): void {
        $this->connection();

        try {
            $this->resolver()->resolve($this->organization, self::CAPABILITY);
            $this->fail('Opt-in-Gate hat nicht gegriffen.');
        } catch (AiUnavailableException $e) {
            $this->assertSame(AiUnavailableException::REASON_CAPABILITY_DISABLED, $e->reason);
        }
    }

    public function test_resolves_default_connection_first(): void {
        $first = $this->connection();
        $second = $this->connection();
        $this->enableCapability([
            'allowed_connection_ids' => [$first->id, $second->id],
            'default_connection_id' => $second->id,
        ]);

        $candidates = $this->resolver()->resolveCandidates($this->organization, self::CAPABILITY);

        $this->assertSame([$second->id, $first->id], array_map(
            static fn (AiProviderConnection $c): int => (int) $c->id,
            $candidates
        ));
    }

    public function test_connection_outside_allowlist_is_never_used(): void {
        $allowed = $this->connection();
        $foreign = $this->connection();
        $this->enableCapability([
            'allowed_connection_ids' => [$allowed->id],
            'allow_user_choice' => true,
        ]);

        try {
            $this->resolver()->resolve($this->organization, self::CAPABILITY, $foreign->id);
            $this->fail('Allowlist hat nicht gegriffen.');
        } catch (AiUnavailableException $e) {
            $this->assertSame(AiUnavailableException::REASON_CONNECTION_NOT_ALLOWED, $e->reason);
        }
    }

    public function test_user_choice_requires_setting(): void {
        $first = $this->connection();
        $second = $this->connection();
        $this->enableCapability([
            'allowed_connection_ids' => [$first->id, $second->id],
            'allow_user_choice' => false,
        ]);

        try {
            $this->resolver()->resolve($this->organization, self::CAPABILITY, $second->id);
            $this->fail('Nutzerwahl-Schalter hat nicht gegriffen.');
        } catch (AiUnavailableException $e) {
            $this->assertSame(AiUnavailableException::REASON_CONNECTION_NOT_ALLOWED, $e->reason);
        }
    }

    public function test_user_choice_moves_requested_connection_to_front(): void {
        $first = $this->connection();
        $second = $this->connection();
        $this->enableCapability([
            'allowed_connection_ids' => [$first->id, $second->id],
            'default_connection_id' => $first->id,
            'allow_user_choice' => true,
        ]);

        $resolved = $this->resolver()->resolve($this->organization, self::CAPABILITY, $second->id);

        $this->assertSame((int) $second->id, (int) $resolved->id);
    }

    public function test_high_sensitivity_filters_cloud_connections(): void {
        config()->set('ai.capabilities.' . self::CAPABILITY . '.sensitivity', 'high');
        $cloud = $this->connection(['is_local' => false]);
        $local = $this->connection(['is_local' => true]);
        $this->enableCapability([
            'allowed_connection_ids' => [$cloud->id, $local->id],
            'default_connection_id' => $cloud->id,
        ]);

        $candidates = $this->resolver()->resolveCandidates($this->organization, self::CAPABILITY);

        $this->assertSame([(int) $local->id], array_map(
            static fn (AiProviderConnection $c): int => (int) $c->id,
            $candidates
        ));
    }

    public function test_pflege_profile_blocks_cloud_connections(): void {
        $this->organization->forceFill([
            'settings' => ['branch_profile_code' => 'pflege'],
        ])->save();

        $cloud = $this->connection(['is_local' => false]);
        $this->enableCapability(['allowed_connection_ids' => [$cloud->id]]);

        try {
            $this->resolver()->resolve($this->organization->fresh(), self::CAPABILITY);
            $this->fail('Pflege-Profil-Gate hat nicht gegriffen.');
        } catch (AiUnavailableException $e) {
            $this->assertSame(AiUnavailableException::REASON_NO_CONNECTION, $e->reason);
        }
    }

    public function test_pflege_profile_in_versions_blocks_cloud_even_after_profile_switch(): void {
        $this->organization->forceFill([
            'settings' => [
                'branch_profile_code' => 'it_service',
                'branch_profile_versions' => ['pflege' => 3, 'it_service' => 1],
            ],
        ])->save();

        $cloud = $this->connection(['is_local' => false]);
        $local = $this->connection(['is_local' => true]);
        $this->enableCapability(['allowed_connection_ids' => [$cloud->id, $local->id]]);

        $candidates = $this->resolver()->resolveCandidates($this->organization->fresh(), self::CAPABILITY);

        $this->assertSame([(int) $local->id], array_map(
            static fn (AiProviderConnection $c): int => (int) $c->id,
            $candidates
        ));
    }

    public function test_draft_blocked_and_unhealthy_connections_are_skipped(): void {
        $draft = $this->connection(['status' => \App\Enums\Ai\AiConnectionStatus::Draft]);
        $blocked = $this->connection(['status' => \App\Enums\Ai\AiConnectionStatus::Blocked]);
        $unhealthy = $this->connection();
        $unhealthy->forceFill(['disabled_at' => now(), 'last_error' => 'x'])->save();
        $healthy = $this->connection();

        $this->enableCapability([
            'allowed_connection_ids' => [$draft->id, $blocked->id, $unhealthy->id, $healthy->id],
        ]);

        $candidates = $this->resolver()->resolveCandidates($this->organization, self::CAPABILITY);

        $this->assertSame([(int) $healthy->id], array_map(
            static fn (AiProviderConnection $c): int => (int) $c->id,
            $candidates
        ));
    }

    public function test_translation_family_connection_does_not_serve_formulate(): void {
        $translation = AiProviderConnection::factory()->translation()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->enableCapability(['allowed_connection_ids' => [$translation->id]]);

        try {
            $this->resolver()->resolve($this->organization, self::CAPABILITY);
            $this->fail('Familien-Filter hat nicht gegriffen.');
        } catch (AiUnavailableException $e) {
            $this->assertSame(AiUnavailableException::REASON_NO_CONNECTION, $e->reason);
        }
    }

    public function test_translate_verb_accepts_both_families(): void {
        config()->set('ai.capabilities.test.translate', [
            'verb' => 'translate',
            'sensitivity' => 'medium',
            'data_classes' => ['text'],
            'memory_scopes' => [],
            'prompt_version' => 1,
        ]);

        $llm = $this->connection();
        $translation = AiProviderConnection::factory()->translation()->create([
            'organization_id' => $this->organization->id,
        ]);
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => 'test.translate',
            'enabled' => true,
            'allowed_connection_ids' => [$translation->id, $llm->id],
        ]);

        $candidates = $this->resolver()->resolveCandidates($this->organization, 'test.translate');

        $this->assertCount(2, $candidates);
    }
}
