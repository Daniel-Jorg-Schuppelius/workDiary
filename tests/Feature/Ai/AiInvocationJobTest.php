<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiInvocationJobTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Jobs\Ai\AiInvocationJob;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection};
use App\Models\Organization;
use App\Services\Ai\Contracts\AiResultHandlerInterface;
use App\Services\Ai\Dto\{AiInvocationResult, FormulateRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{RegistersAiCapabilities, WithOrganization};
use Tests\Support\FakeAiProviderFactory;
use Tests\TestCase;

/**
 * Asynchroner KI-Aufruf (MVP-399): Handler-Zustellung, Modul-Gate vor
 * der Wirkung und terminale Fehlerpfade ohne Retry.
 */
class AiInvocationJobTest extends TestCase {
    use RefreshDatabase;
    use RegistersAiCapabilities;
    use WithOrganization;

    private const CAPABILITY = 'test.formulate';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        FakeAiProviderFactory::install();
        RecordingAiHandler::reset();

        $this->registerAiCapability(self::CAPABILITY);
    }

    public function test_job_delivers_result_to_handler(): void {
        $connection = AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => self::CAPABILITY,
            'enabled' => true,
            'allowed_connection_ids' => [$connection->id],
        ]);

        AiInvocationJob::dispatchSync(
            (int) $this->organization->id,
            self::CAPABILITY,
            new FormulateRequest(text: 'Wartung Clients'),
            RecordingAiHandler::class,
            ['idempotency_key' => 'test-1']
        );

        $this->assertCount(1, RecordingAiHandler::$results);
        $this->assertSame('test-1', RecordingAiHandler::$results[0]['context']['idempotency_key']);
        $this->assertSame([], RecordingAiHandler::$failures);
    }

    public function test_module_gate_makes_job_a_noop_on_free_plan(): void {
        $freeOrg = Organization::factory()->free()->create();
        $connection = AiProviderConnection::factory()->create(['organization_id' => $freeOrg->id]);
        AiCapabilitySetting::factory()->create([
            'organization_id' => $freeOrg->id,
            'capability' => self::CAPABILITY,
            'enabled' => true,
            'allowed_connection_ids' => [$connection->id],
        ]);

        AiInvocationJob::dispatchSync(
            (int) $freeOrg->id,
            self::CAPABILITY,
            new FormulateRequest(text: 'x'),
            RecordingAiHandler::class
        );

        $this->assertSame([], RecordingAiHandler::$results);
        $this->assertSame([], RecordingAiHandler::$failures);
    }

    public function test_disabled_capability_reports_terminal_failure_without_retry(): void {
        AiInvocationJob::dispatchSync(
            (int) $this->organization->id,
            self::CAPABILITY,
            new FormulateRequest(text: 'x'),
            RecordingAiHandler::class,
            ['idempotency_key' => 'test-2']
        );

        $this->assertSame([], RecordingAiHandler::$results);
        $this->assertCount(1, RecordingAiHandler::$failures);
        $this->assertSame('capability_disabled', RecordingAiHandler::$failures[0]['reason']);
    }
}

/**
 * Test-Handler: zeichnet Ergebnisse/Fehlschläge statisch auf (der Job
 * löst die Klasse über den Container neu auf — Instanzzustand trüge nicht).
 */
class RecordingAiHandler implements AiResultHandlerInterface {
    /** @var list<array{result: AiInvocationResult, context: array<string, mixed>}> */
    public static array $results = [];

    /** @var list<array{reason: string, context: array<string, mixed>}> */
    public static array $failures = [];

    public static function reset(): void {
        self::$results = [];
        self::$failures = [];
    }

    public function handleAiResult(AiInvocationResult $result, array $context): void {
        self::$results[] = ['result' => $result, 'context' => $context];
    }

    public function handleAiFailure(string $reason, array $context): void {
        self::$failures[] = ['reason' => $reason, 'context' => $context];
    }
}
