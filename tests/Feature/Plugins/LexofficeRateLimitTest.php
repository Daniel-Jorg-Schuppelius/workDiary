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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Sichert ab, dass ein Lexoffice-Rate-Limit (HTTP 429) als *transienter*
 * Zustand behandelt wird: Der Healthcheck meldet `degraded` (nicht `failing`),
 * damit der Auto-Disable-Zähler nicht hochläuft, und die rohen HTTP-Aufrufe
 * versuchen es bei 429 automatisch erneut.
 */
class LexofficeRateLimitTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Sleep::fake(); // Backoff nicht real abwarten
    }

    private function service(): LexofficeService {
        return new LexofficeService('test-key', new LexofficeMapper);
    }

    public function test_healthcheck_returns_degraded_on_persistent_rate_limit(): void {
        Http::fake([
            'https://api.lexoffice.io/v1/profile' => Http::response('rate limited', 429),
        ]);

        $health = (new LexofficePlugin($this->service()))->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->status);
        // Drei Versuche gegen denselben Endpunkt (retry(3) = 3 Versuche gesamt).
        Http::assertSentCount(3);
    }

    public function test_ping_throws_rate_limit_exception_after_exhausting_retries(): void {
        Http::fake([
            'https://api.lexoffice.io/v1/profile' => Http::response('rate limited', 429),
        ]);

        $this->expectException(LexofficeRateLimitException::class);

        $this->service()->ping();
    }

    public function test_ping_recovers_when_rate_limit_clears_on_retry(): void {
        Http::fakeSequence('https://api.lexoffice.io/v1/profile')
            ->push('rate limited', 429)
            ->push(['organizationId' => 'org-1'], 200);

        $this->assertTrue($this->service()->ping());
    }
}
