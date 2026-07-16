<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiInvocationServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\Ai\AiFamily;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection, AiUsagePeriod};
use App\Models\AuditLog;
use App\Services\Ai\AiInvocationService;
use App\Services\Ai\Dto\{AiTextResult, ClassifyRequest, FormulateRequest};
use App\Services\Ai\Exceptions\{AiBudgetExceededException, AiUnavailableException};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{RegistersAiCapabilities, WithOrganization};
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * Ausführungspfad des KI-Fundaments (Feature 025, MVP-399): Fake-Provider,
 * Ergebnis-Cache, Budget-Gate, Health-Tracking, Fallback-Kette, Audit
 * ohne Klartext und Katalog-Garantie beim Klassifizieren.
 */
class AiInvocationServiceTest extends TestCase {
    use RefreshDatabase;
    use RegistersAiCapabilities;
    use WithOrganization;

    private const CAPABILITY = 'test.formulate';

    private FakeAiProvider $fake;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->fake = FakeAiProviderFactory::install();

        $this->registerAiCapability(self::CAPABILITY);
    }

    private function service(): AiInvocationService {
        return app(AiInvocationService::class);
    }

    private function setUpCapability(int ...$connectionIds): void {
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => self::CAPABILITY,
            'enabled' => true,
            'allowed_connection_ids' => $connectionIds,
            'default_connection_id' => $connectionIds[0] ?? null,
        ]);
    }

    private function connection(): AiProviderConnection {
        return AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    private function request(string $text = 'Wartung Clients und Server installation snap'): FormulateRequest {
        return new FormulateRequest(text: $text);
    }

    public function test_invoke_returns_result_and_records_usage_and_audit(): void {
        $connection = $this->connection();
        $this->setUpCapability((int) $connection->id);
        $this->fake->textResponse = 'Wartung der Client- und Server-Systeme; Installation von Snap-Paketen';

        $result = $this->service()->invoke($this->organization, self::CAPABILITY, $this->request());

        $this->assertInstanceOf(AiTextResult::class, $result->result);
        $this->assertSame('Wartung der Client- und Server-Systeme; Installation von Snap-Paketen', $result->result->text);
        $this->assertFalse($result->fallbackUsed);
        $this->assertFalse($result->fromCache);

        $usage = AiUsagePeriod::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('family', AiFamily::Llm->value)
            ->first();
        $this->assertNotNull($usage);
        $this->assertSame(15, $usage->used_units); // 10 in + 5 out

        $audit = AuditLog::query()->where('event', 'ai.invoked')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame(self::CAPABILITY, data_get($audit->changes, 'capability'));
        // Kein Klartext im Audit: weder Eingabe- noch Antworttext.
        $this->assertStringNotContainsString('Wartung', (string) json_encode($audit->changes));
    }

    public function test_identical_request_is_served_from_cache_without_provider_call(): void {
        $connection = $this->connection();
        $this->setUpCapability((int) $connection->id);

        $first = $this->service()->invoke($this->organization, self::CAPABILITY, $this->request());
        $second = $this->service()->invoke($this->organization, self::CAPABILITY, $this->request());

        $this->assertFalse($first->fromCache);
        $this->assertTrue($second->fromCache);
        $this->assertSame(1, $this->fake->callCount('formulate'));
    }

    public function test_prompt_version_bump_invalidates_cache(): void {
        $connection = $this->connection();
        $this->setUpCapability((int) $connection->id);

        $this->service()->invoke($this->organization, self::CAPABILITY, $this->request());
        $this->registerAiCapability(self::CAPABILITY, ['prompt_version' => 2]);
        $second = $this->service()->invoke($this->organization, self::CAPABILITY, $this->request());

        $this->assertFalse($second->fromCache);
        $this->assertSame(2, $this->fake->callCount('formulate'));
    }

    public function test_budget_gate_blocks_before_provider_call(): void {
        $connection = $this->connection();
        $this->setUpCapability((int) $connection->id);
        $this->organization->forceFill([
            'settings' => ['ai' => ['budget' => ['monthly_units' => ['llm' => 5]]]],
        ])->save();

        $this->expectException(AiBudgetExceededException::class);

        try {
            $this->service()->invoke($this->organization->fresh(), self::CAPABILITY, $this->request());
        } finally {
            $this->assertSame(0, $this->fake->callCount('formulate'));
        }
    }

    public function test_failed_connection_falls_back_to_next_candidate_and_records_health(): void {
        $primary = $this->connection();
        $secondary = $this->connection();
        $this->setUpCapability((int) $primary->id, (int) $secondary->id);
        FakeAiProviderFactory::current()->failFor($primary);

        $result = $this->service()->invoke($this->organization, self::CAPABILITY, $this->request());

        $this->assertSame((int) $secondary->id, $result->connectionId);
        $this->assertTrue($result->fallbackUsed);

        $primary->refresh();
        $this->assertSame(1, $primary->consecutive_failures);
        $this->assertNotNull($primary->last_error);
        $this->assertStringNotContainsString('Wartung', (string) $primary->last_error);
    }

    public function test_all_failing_connections_raise_unavailable(): void {
        $only = $this->connection();
        $this->setUpCapability((int) $only->id);
        FakeAiProviderFactory::current()->failFor($only);

        try {
            $this->service()->invoke($this->organization, self::CAPABILITY, $this->request());
            $this->fail('Fallback-Ende hat nicht gegriffen.');
        } catch (AiUnavailableException $e) {
            $this->assertSame(AiUnavailableException::REASON_ALL_FAILED, $e->reason);
            $this->assertTrue($e->isRetryable());
        }
    }

    public function test_classification_never_returns_values_outside_catalog(): void {
        $this->registerAiCapability('test.classify', ['verb' => 'classify', 'sensitivity' => 'low']);
        $connection = $this->connection();
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => 'test.classify',
            'enabled' => true,
            'allowed_connection_ids' => [$connection->id],
        ]);
        $this->fake->classificationResponse = ['wartung', 'halluziniertes-label'];

        $result = $this->service()->invoke(
            $this->organization,
            'test.classify',
            new ClassifyRequest(text: 'Serverwartung', catalog: ['wartung', 'installation'])
        );

        $this->assertInstanceOf(\App\Services\Ai\Dto\AiClassificationResult::class, $result->result);
        $this->assertSame(['wartung'], $result->result->values);
    }

    public function test_verb_mismatch_is_rejected(): void {
        $connection = $this->connection();
        $this->setUpCapability((int) $connection->id);

        $this->expectException(\App\Services\Ai\Exceptions\AiException::class);

        $this->service()->invoke(
            $this->organization,
            self::CAPABILITY,
            new ClassifyRequest(text: 'x', catalog: ['a'])
        );
    }
}
