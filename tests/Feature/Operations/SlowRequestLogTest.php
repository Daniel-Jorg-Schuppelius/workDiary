<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlowRequestLogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Operations;

use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SlowRequestLogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_request_above_threshold_is_logged_with_route_and_query_count(): void {
        config(['performance.slow_request_ms' => 1]);
        Log::spy();

        $this->get('/login?secret=abc')->assertOk();

        Log::shouldHaveReceived('warning')->withArgs(static function (string $message, array $context): bool {
            return $message === 'Langsame Anfrage'
                && $context['route'] === 'login'
                && $context['method'] === 'GET'
                && $context['status'] === 200
                && is_int($context['queries'])
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secret');
        })->once();
    }

    public function test_nothing_is_logged_below_threshold_or_when_disabled(): void {
        Log::spy();

        config(['performance.slow_request_ms' => 600000]);
        $this->get('/login')->assertOk();
        config(['performance.slow_request_ms' => 0]);
        $this->get('/login')->assertOk();

        Log::shouldNotHaveReceived('warning', [\Mockery::any(), \Mockery::on(static fn ($context): bool => is_array($context) && array_key_exists('query_ms', $context))]);
    }

    public function test_server_timing_header_only_for_signed_in_users_when_enabled(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        config(['performance.slow_request_ms' => 0, 'performance.server_timing' => true]);

        $this->assertNull($this->get('/login')->headers->get('Server-Timing'));

        $header = (string) $this->actingAs($user)->get('/account/bookmarks')->headers->get('Server-Timing');
        $this->assertMatchesRegularExpression('/^app;dur=[\d.]+, db;dur=[\d.]+;desc="\d+"$/', $header);

        config(['performance.server_timing' => false]);
        $this->assertNull($this->actingAs($user)->get('/account/bookmarks')->headers->get('Server-Timing'));
    }
}
