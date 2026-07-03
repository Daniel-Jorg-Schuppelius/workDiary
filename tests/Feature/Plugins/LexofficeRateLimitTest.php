<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRateLimitTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Plugins\Lexoffice\{LexofficeMapper, LexofficePlugin, LexofficeRateLimitException, LexofficeService};
use App\Plugins\PluginHealth;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Sichert ab, dass ein Lexoffice-Rate-Limit (HTTP 429) als *transienter*
 * Zustand behandelt wird: Der Healthcheck meldet `degraded` (nicht `failing`),
 * damit der Auto-Disable-Zähler nicht hochläuft, und die rohen HTTP-Aufrufe
 * versuchen es bei 429 automatisch erneut.
 */
class LexofficeRateLimitTest extends TestCase {
    private function service(): LexofficeService {
        return new LexofficeService('test-key', new LexofficeMapper);
    }

    public function test_healthcheck_returns_degraded_on_persistent_rate_limit(): void {
        $fake = FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => FakePluginHttp::response('rate limited', 429),
        ]);

        $health = (new LexofficePlugin($this->service()))->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->status);
        // Drei Versuche gegen denselben Endpunkt (maxRetries=3 = 3 Versuche gesamt).
        $fake->assertSentCount(3);
    }

    public function test_ping_throws_rate_limit_exception_after_exhausting_retries(): void {
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => FakePluginHttp::response('rate limited', 429),
        ]);

        $this->expectException(LexofficeRateLimitException::class);

        $this->service()->ping();
    }

    public function test_ping_recovers_when_rate_limit_clears_on_retry(): void {
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => [
                FakePluginHttp::response('rate limited', 429),
                FakePluginHttp::response(['organizationId' => 'org-1'], 200),
            ],
        ]);

        $this->assertTrue($this->service()->ping());
    }

    public function test_healthcheck_returns_degraded_on_server_error(): void {
        // 5xx ist serverseitig/transient → degraded (kein Auto-Disable).
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => FakePluginHttp::response('boom', 503),
        ]);

        $health = (new LexofficePlugin($this->service()))->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->status);
        $this->assertStringContainsString('503', $health->message);
    }

    public function test_healthcheck_returns_failing_with_status_on_client_error(): void {
        // 403 (z. B. fehlender Scope/Tarif) → failing, Status in der Meldung.
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => FakePluginHttp::response('forbidden', 403),
        ]);

        $health = (new LexofficePlugin($this->service()))->healthCheck();

        $this->assertSame(PluginHealth::STATUS_FAILING, $health->status);
        $this->assertStringContainsString('403', $health->message);
    }
}
